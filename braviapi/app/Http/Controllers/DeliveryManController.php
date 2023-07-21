<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use App\Traits\FilesTrait;
use App\Validation\DeliverymanValidation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Actions\Admin\Deliveryman\SetHardware;
use Packk\Core\Actions\Admin\Deliveryman\TempBanShipper;
use Packk\Core\Integration\Payment\ProcessPayment;
use Packk\Core\Integration\PrePaidCard\Card;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\User;
use Packk\Core\Models\BankAccount;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\DeliverymanStatus;
use Packk\Core\Models\Hardware;
use Packk\Core\Models\Reason;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Util\Firebase;
use Packk\Core\Util\Phones;

class DeliveryManController extends Controller
{
    use FilesTrait;

    public function index(Request $request)
    {
        $query = Deliveryman::withoutGlobalScope(DomainScope::class)
            ->join('users', 'users.id', '=', 'entregadores.user_id')
            ->where(function($q) use($request) {
                $q->where('users.nome', 'like', "{$request->nome}%")
                    ->orWhere('users.sobrenome', 'like', "{$request->nome}%");
            })->like('users.email', $request->email)
            ->like('users.telefone', $request->telefone)
            ->identic('entregadores.id', $request->id)
            ->identic('entregadores.domain_id', $request->domain_id)
            ->selectRaw('
                entregadores.*,
                CONCAT(entregadores.id, " - ",users.nome) as id_nome,
                CONCAT(users.nome, " ",users.sobrenome) as nome_completo,
                users.nome,
                users.sobrenome,
                users.foto_perfil,
                users.cpf,
                users.email,
                users.telefone,
                case entregadores.tipo_veiculo 
                    when "B" then "Bike"
                    when "C" then "Carro"
                    when "M" then "Moto"
                end as veiculo_desc
                ')
            ->orderBy('users.nome');

        if ($request->pre_actived) {
            $query->whereIn('entregadores.estado', ['N', 'REJECTED']);
        } else {
            $query->identic('entregadores.estado', 'A');
        }

        return $query->simplePaginate($request->length);
    }

    public function show(Request $request, $id)
    {
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)
            ->join('users', 'users.id', '=', 'entregadores.user_id')
            ->selectRaw('
                    entregadores.*,
                    CONCAT(users.nome, " ",users.sobrenome) as nome_completo,
                    users.nome,
                    users.sobrenome,
                    users.foto_perfil,
                    users.cpf,
                    users.email,
                    users.telefone,
                    case entregadores.tipo_veiculo 
                        when "B" then "Bike"
                        when "C" then "Carro"
                        when "M" then "Moto"
                    end as veiculo_desc
                        ')
            ->find($id);

        if ($request->score) {
            $entregador = $entregador->getScore();
        }
        if ($request->bankAccount) {
            $conta = $entregador->has_bank_account();
            $entregador = array_merge($entregador->toArray(), $conta);
        }
        if ($request->reason) {
            $entregador['reasons'] = Reason::withoutGlobalScope(DomainScope::class)
                ->where('tipo', 'REJEITAR_ENTREGADOR')
                ->where('service_provider', 'ADMIN')
                ->where('domain_id', $entregador['domain_id'])
                ->get();
        }

        $entregador['documento_veiculo'] = $this->convertUri($entregador['documento_veiculo']);
        $entregador['carteira_motorista'] = $this->convertUri($entregador['carteira_motorista']);
        $entregador['rg'] = $this->convertUri($entregador['rg']);

        return $entregador;
    }

    public function edit($id)
    {
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);
        /*if (isset($entregador->ppc_id)) {
            $card = new Card($entregador->ppc_id);
            $entregador->ppc_id = $card->read()->service_id;
        }*/
        $response = $entregador->toArray();

        return array_merge($response, [
            "nome" => $entregador->user->nome,
            "sobrenome" => $entregador->user->sobrenome,
            "foto_perfil" => $entregador->user->foto_perfil,
            "cpf" => $entregador->user->cpf,
            "email" => $entregador->user->email,
            "telefone" => $entregador->user->telefone,
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, [
            'nome' => 'required',
            'sobrenome' => 'required',
            'email' => 'required',
            'telefone' => 'required',
        ]);
        $payload['telefone'] = Phones::format($payload['telefone']);
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);

        $count = User::withoutGlobalScope(DomainScope::class)
            ->where("id", '!=', $entregador->user_id)
            ->where('telefone', $payload['telefone'])
            ->where('tipo', 'E')
            ->count();

        if ($count > 0) {
            throw new \Exception("O telefone está associado a outro entregador", 500);
        }

        if (!empty($request->senha)) {
            $payload['password'] = bcrypt($request->senha);
        }
        User::withoutGlobalScope(DomainScope::class)
            ->where("id", $entregador->user_id)
            ->update($payload);

        $entregador->tipo_veiculo = $request->tipo_veiculo;

        if (isset($request->ppc_id)) {
            $card = new Card($entregador->ppc_id);
            $card_service = $card->service($request->ppc_id);
            if (isset($card_service) && isset($card_service->id) && $card_service->id != $entregador->ppc_id) {
                $entregador->ppc_id = $card_service->id;
                $card->update(["reference_id" => $entregador->id]);
            }
        } else {
            $entregador->ppc_id = null;
        }
        $entregador->save();

        return response(['success' => true]);
    }

    public function bankAccount(Request $request, $id)
    {
        $payload = $this->validate($request, [
            'favorecido' => 'required',
            'codigo_banco' => 'required',
            'agencia' => 'required',
            'numero' => 'required',
        ]);

        try {
            $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);

            \DB::beginTransaction();

            $zoop = new ProcessPayment();
            $conta = $entregador->contas_bancarias()->first();
            $is_conta = $conta == null;
            if ($is_conta) {
                $conta = new BankAccount();
            }
            $conta->favorecido = $request->favorecido;
            $conta->banco = $request->codigo_banco;
            $conta->agencia = $request->agencia;
            $conta->conta = $request->numero;
            $conta->entregador_id = $entregador->id;
            $conta->tipo = $request->tipo ?? 'checking';
            $conta->save();

            $contas_antigas = $zoop->getContasBancarias($entregador);
            $zoop->associaContaEntregador(
                $entregador,
                $request->favorecido,
                $request->codigo_banco,
                $request->agencia,
                $request->numero,
                $conta->tipo
            );

            $entregador->estado = 'A';
            $entregador->save();
            $zoop->deleteContasBancarias($contas_antigas);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            return Responser::response(['message' => $e->getMessage()], Responser::SERVER_ERROR);
        }

        return response([
            'success' => true,
        ]);
    }

    public function refuse(Request $request, int $id)
    {
        $this->validate($request, [
            'motivos' => 'required'
        ], [
            'required' => 'O :attribute não pode ser vazio'
        ]);

        try {
            DeliverymanStatus::where('entregador_id', $id)->whereNull('canceled_at')->update(['canceled_at' => now()]);

            foreach ($request->motivos as $key => $motivo) {
                $rejectReasons = new DeliverymanStatus();
                $rejectReasons->entregador_id = $id;
                $rejectReasons->reason_id = $motivo;
                $rejectReasons->save();
            }

            if (!empty($request->observacao)) {
                $rejectReasons = new DeliverymanStatus();
                $rejectReasons->entregador_id = $id;
                $rejectReasons->comment = $request->observacao;
                $rejectReasons->save();
            }

            $entregador = Deliveryman::find($id);
            $entregador->estado = 'REJECTED';
            $entregador->save();

            $entregador->user->addAtividade('REJECT_SHIPPER', ['[::nome_entregador]' => $entregador->user->nome_completo], auth()->user()->id, 'ADMIN');

            $fb = new Firebase();
            $fb->sendDirectMessage(
                $entregador->user->email,
                [
                    "tipo" => "mensagem_admin_cliente",
                    "titulo" => "Cadastro rejeitado",
                    "mensagem" => "Após analise dos dados cadastrados, sua solicitação de cadastro foi rejeitada, confira os dados enviados."
                ]
            );
            return response(['success' => true]);
        } catch (\Exception $e) {
            return response(['success' => false, 'error' => 'Falha ao rejeitar entregador: ' . $e->getMessage()], 500);
        }
    }

    public function preActive(Request $request, $id)
    {
        $payload = $this->validate($request, [
            'pre_ativado' => 'required',
            'favorecido' => 'required',
            'codigo_banco' => 'required',
            'agencia' => 'required',
            'numero' => 'required',
        ]);

        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);
        $conta = BankAccount::updateOrCreate(
            ['entregador_id' => $id],
            [
                'banco' => $request->codigo_banco,
                'agencia' => $request->agencia,
                'conta' => $request->numero,
                'favorecido' => $request->favorecido
            ]
        );
        $conta->save();

        $entregador->pre_ativado = $request->pre_ativado;
        $entregador->save();

        return response([
            'success' => true,
        ]);
    }

    public function hardwares($id)
    {
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);

        $available = Hardware::whereDoesntHave('users', function ($query) use ($entregador) {
            $query->where("user_id", $entregador->user_id);
        })->get();

        $active = $entregador->user->hardwares()->get();

        return response()->json([
            'active' => $active,
            'available' => $available,
        ]);

    }

    public function hardwareStore(Request $request, SetHardware $setHardware, $id)
    {
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);
        $request->merge(['user_id' => $entregador->user_id]);

        $validate = $this->validate($request, DeliverymanValidation::hardwareRules());
        $validate["register_user_id"] = Auth::user()->id;
        $payload = [
            "user_id" => $validate["user_id"],
            "hardware_id" => $validate["hardware_id"],
            "data" => $validate
        ];

        return $setHardware->execute($payload);
    }

    public function hardwareUpdate(Request $request, SetHardware $setHardware, $id, $hardware_id)
    {
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);
        $request->merge([
            'user_id' => $entregador->user_id,
            'id' => $hardware_id
        ]);

        $payload = $this->validate($request, DeliverymanValidation::updateHardwareRules());
        return $setHardware->active($payload);
    }

    public function hardwareRemove(Request $request, $id, $hardware_id)
    {
        $entregador = Deliveryman::withoutGlobalScope(DomainScope::class)->find($id);

        $entregador->user->hardwares()
            ->wherePivot('deleted_at', null)
            ->updateExistingPivot($hardware_id, ['deleted_at' => Carbon::now()]);

        return response(true);
    }

    public function suspend(Request $request, $id)
    {
        $this->validate($request, [
            'motivo' => 'required',
            'observacao' => 'required',
        ]);

        try {
            $tempBan = new TempBanShipper();
            $data = [
                'entregador_id' => $id,
                'motivo_id' => $request->motivo
            ];
            $tempBan->execute($data, 'ADMIN_TEMPORARY_BAN_SHIPPER', $request->observacao, 'ADMIN');
            return ApiResponse::sendResponse();
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function repair(Request $request, $id)
    {
        try {
            $entregador = Deliveryman::find($id);

            $msg = [
                '[::user]' => Auth::user()->id . ' - ' . Auth::user()->nome . ' ' . Auth::user()->sobrenome,
                '[::shipper]' => $entregador->id . " - {$entregador->user->nome} {$entregador->user->sobrenome}",
                //'[::obs]' => $request->justification
            ];

            $entregador->user->addAtividade('ADMIN_REMOVE_TEMPORARY_BAN_SHIPPER', $msg, Auth::user()->id, 'ADMIN');
            $entregador->suspended_until = null;
            $entregador->banido = 0;
            $entregador->save();

            return response([
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response([
                'success' => false,
            ]);
        }
    }

    public function destroy($id)
    {
        $user = User::withoutGlobalScope(DomainScope::class)->findOrFail($id);
        $user->delete();
        return response(true);
    }

    public function regioes(Request $request)
    {
        return AreaServed::withoutGlobalScope(DomainScope::class)
            ->join('firebase_topics', 'firebase_topics.id', '=', 'zonas_atendidas.firebase_topic_id')
            ->where('firebase_topics.type', 'ENTREGADOR')
            ->selectRaw('regiao, cidade as name')->get();
    }
}
