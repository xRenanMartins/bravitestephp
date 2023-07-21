<?php

namespace App\Http\Controllers;

use App\Rules\Customer\ActivateCustomer;
use App\Rules\Customer\ConsultCpf;
use App\Rules\Customer\ExportCustomer;
use App\Rules\Customer\GetEditCustomer;
use App\Rules\Customer\searchFace;
use App\Rules\Customer\UpdateClient;
use App\Rules\PushScheduled\CreatePush;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Packk\Core\Actions\Admin\Customer\BanCustomer;
use Packk\Core\Actions\Admin\Customer\DeleteCustomer;
use Packk\Core\Actions\Admin\Customer\UnbanCustomer;
use Packk\Core\Integration\Payment\Transaction;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Reason;
use Packk\Core\Models\User;
use Packk\Core\Models\UserAudit;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Integration\Payment\Card;
use Packk\Core\Util\Firebase;
use Packk\Core\Integration\Zenvia\Webphone;
use Packk\Core\Jobs\Customer\CheckDatavalid;
use Packk\Core\Util\Phones;
use Packk\Core\Actions\Admin\Customer\VerifyCrediCard;
use Packk\Core\Models\Order;

class CustomerController extends Controller
{
    public function configScreen(Request $request)
    {
        $user = self::getUserAuth($request, true);
        if (is_null($user)) {
            return response()->json(['message' => 'Token inválido'], 406);
        }
        $domain = currentDomain(true);

        $clientsInAnalysis = Customer::select('clientes.id')
            ->join('users', 'clientes.user_id', 'users.id')
            ->where('tipo', '<>', 'L')
            ->where("status", "EM_ANALISE")->count();

        $clientsBan = Customer::select('clientes.id')
            ->join('users', 'clientes.user_id', 'users.id')
            ->where('tipo', '<>', 'L')
            ->where("banido", 1)->count();

        return [
            'domain_id' => $domain->id,
            'is_zaitt' => $domain->hasFeature("zaittStores"),
            'customer_image' => $domain->hasFeature('customer_image'),
            'export_customer' => $domain->hasFeature('export_customer'),
            'default_message' => $domain->getSetting("text_send_whatsapp_default", "Olá"),
            'check_datavalid' => $domain->getSetting("check_datavalid", false),
            'in_analysis_count' => $clientsInAnalysis,
            'banned_count' => $clientsBan,
            'is_master' => (Auth::check() && Auth::user()->hasRole('master|owner')) || (!empty($user) && in_array($user->tipo, ['M', 'O'])),
            'is_franchisee' => !empty($user) && $user->isFranchiseOperator()
        ];
    }

    public function index(Request $request)
    {
        $user = self::getUserAuth($request, true);
        if (is_null($user)) {
            return response()->json(['message' => 'Token inválido'], 406);
        }

        $query = Customer::query();
        if ((Auth::check() && $user->hasAdminPrivileges()) || $user->tipo === 'M') {
            $query->withoutGlobalScope(DomainScope::class);

            if (!empty($request->domain_id)) {
                $query->where("clientes.domain_id", $request->domain_id);
            }
        }

        $query->join('users', 'users.id', '=', 'clientes.user_id')
            ->identic('clientes.id', $request->id)
            ->identic('clientes.e_funcionario', $request->e_funcionario);

        if (!empty($request->ban)) {
            $query->where("clientes.banido", 1);
        }
        if (!empty($request->in_analysis)) {
            $query->where("status", 'EM_ANALISE');
        }
        if (!empty($request->users_id)) {
            $query->whereIn("users.id", explode(',', $request->users_id));
        }
        if (!empty($request->email)) {
            $query->whereRaw("users.email like '{$request->email}%'");
        }
        if (!empty($request->cpf)) {
            $query->whereRaw("users.cpf like '{$request->cpf}%'");
        }
        if (!empty($request->telefone)) {
            $query->whereRaw("users.telefone like '{$request->telefone}%'");
        }
        if (!empty($request->nome)) {
            $query->whereRaw("CONCAT(users.nome, ' ', users.sobrenome) like '{$request->nome}%'");
        }

        $data = $query->selectRaw('clientes.*,
                        users.nome,
                        users.sobrenome,
                        users.borned_at,
                        users.foto_perfil_s3,
                        users.cpf,
                        users.email,
                        users.telefone,
                        users.status')
            ->orderByDesc('clientes.id')
            ->simplePaginate($request->length);

        $response = $data->toArray();
        foreach ($data->items() as $key => $item) {
            $response['data'][$key]['telefone'] = empty($item->telefone) ? null : Phones::formatExibe($item->telefone);
        }
        return $response;
    }

    public function edit(Request $request, $id, GetEditCustomer $editCustomer)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        return $editCustomer->execute($id, $request->unBanForm);
    }

    public function update(Request $request, $id, UpdateClient $updateClient)
    {
        $user = self::getUserAuth($request, true);
        if (is_null($user)) {
            return response()->json(['message' => 'Token inválido'], 406);
        }
        return $updateClient->execute($request, $id, $user);
    }

    public function ban(Request $request, $id, BanCustomer $banCustomer)
    {
        try {
            $user = self::getUserAuth($request, true);
            if (is_null($user)) {
                return response()->json(['message' => 'Token inválido'], 406);
            }
            $request->merge([
                'customer_id' => $id
            ]);

            $banResponse = $banCustomer->execute($request, $user);

            if ($banResponse['status'] == 200) {
                return ['success' => true, 'message' => 'Cliente banido'];
            } else {
                return ['success' => false, 'message' => $banResponse['erro']];
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function unban(Request $request, $id, UnbanCustomer $unbanCustomer)
    {
        $user = self::getUserAuth($request, true);
        if (is_null($user)) {
            return response()->json(['message' => 'Token inválido'], 406);
        }
        $request->merge(['customer_id' => $id]);

        $unbanResponse = $unbanCustomer->execute($request, $user);
        if ($unbanResponse['status'] == 200) {
            return ['success' => true, 'message' => 'Cliente desbanido'];
        } else {
            return $unbanResponse;
        }
    }

    public function delete(Request $request, $id, DeleteCustomer $deleteCustomer)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $client = Customer::withoutGlobalScope(DomainScope::class)->findOrFail($id);
        
        if(Order::where('cliente_id', $id)->where('estado', '<>', 'F')->where('estado', '<>', 'C')->exists()){
            return response()->json(['message' => ' Não é possível excluir o cliente ID do cliente, pois o mesmo possui pedido(s) pendente(s).'], 404);
        }
        $deleteCustomer->execute($client);
        return response(['success' => true]);
    }

    private static function getUserAuth(Request $request, $model = false)
    {
        if (empty($request->token)) {
            return $model ? Auth::user() : Auth::id();
        }

        $setting = DB::table('setting_user')
            ->selectRaw('setting_user.user_id')
            ->join('settings', 'settings.id', '=', 'setting_user.setting_id')
            ->where('settings.label', 'customers_token_view')
            ->where('setting_user.value', $request->token)
            ->first();
        if (empty($setting)) {
            return null;
        }
        return $model ? User::findOrFail($setting->user_id) : $setting->user_id;
    }

    public function showCards(Request $request, $id, Card $card)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $cards = $card->customerCardsLegacy($id);

        $formatted_cards = [];
        $fingerprints = [];
        foreach (collect($cards)->groupBy("fingerprint") as $fingerprint => $card) {
            $formatted_cards[] = $card->first();
            $fingerprints[] = $fingerprint;
        }

        $references = [];
        foreach ($fingerprints as $item) {
            $references[] = "'{$item}'";
        }
        $references = implode(',', $references);

        if (!empty($references)) {
            $audit = UserAudit::where("reference_provider", "TRANSACTION")
                ->where("type", "RANDOM_TRANSACTION")
                ->whereRaw("parent_reference_id in ({$references})")
                ->where("action", "!=", "FAIL")
                ->get()
                ->toArray();
        }

        return [
            'cards' => $formatted_cards,
            'audit' => $audit ?? [],
        ];
    }

    public function sendFirebaseMessage(Request $request, $id, Firebase $fb)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $mensagem = [
            'tipo' => 'mensagem_admin_cliente',
            'titulo' => $request->title,
            'mensagem' => $request->message,
            'redirect_to' => config('globals.app.screens.news')
        ];

        $customer = Customer::withoutGlobalScope(DomainScope::class)->findOrFail($id);

        $fb->sendClienteMultiCastMessageV2([$customer->user_id], $mensagem);
        (new CreatePush())->execute($request->title, $request->message, [$customer->user_id], "I");

        return response()->json(['status' => 1]);
    }

    public function getWebphone(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $domain = currentDomain(true);
        $branch = $domain->getSetting("zenvia_default_extension_phone", null);

        if (empty($branch)) {
            return response()->json(["mensagem" => "Domínio não está com um ramal configurado"], 404);
        }

        $webPhone = new Webphone(true);
        $webphone = $webPhone->getWebphone($branch, substr($customer->user->telefone, 1));

        if ($webphone->get('status') == 200) {
            $user = self::getUserAuth($request);
            if (is_null($user)) {
                return response()->json(['message' => 'Token inválido'], 406);
            }
            $customer->user->addAtividade("USER_FINISH_CALL", ['[::response]' => null], $user, 'ADMIN');
            return response()->json($webphone->get('data'));
        } else {
            $error = $webphone->get('error');
            $error = !empty($error) ? json_decode($error) : 'Falha na requisição da Zenvia';
            return response()->json($error, $webphone->get('status', 400));
        }
    }

    public function getDatavalidFace(Request $request, $id)
    {
        $domain = currentDomain(true);
        $customer = Customer::findOrFail($id);

        if ($domain->getSetting('check_datavalid', false)) {
            $user = self::getUserAuth($request);
            if (is_null($user)) {
                return response()->json(['message' => 'Token inválido'], 406);
            }
            dispatch(new CheckDatavalid($customer, $user, 'ADMIN'));
        }
        return ["success" => true];
    }

    public function getBigID(Request $request, $id, ConsultCpf $consultCpf)
    {
        try {
            $result = self::getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $customer = Customer::findOrFail($id);
            return response()->json($consultCpf->execute($customer->user), 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), $e->getCode());
        }
    }

    public function getReasons(Request $request)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        return Reason::withoutGlobalScope(DomainScope::class)
            ->identic('domain_id', $request->domain_id)
            ->identic('tipo', $request->type)
            ->identic('service_provider', $request->service_provider)
            ->orderBy('descricao')->get();
    }

    public function makeTransactionToValidateCard(Request $request, $id, VerifyCrediCard $verifyCrediCard)
    {
        try {
            $result = self::getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $payload = $this->payload($request);
            $payload->client_id = $id;
            $payload->service = "WEB";

            return $verifyCrediCard->execute($payload);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function verifyCardResult(Request $request, $id)
    {
        try {
            $result = self::getUserAuth($request);
            if (is_null($result)) {
                return response()->json(['message' => 'Token inválido'], 426);
            }
            $payload = $this->payload($request);
            $customer = Customer::find($id);

            $audit = $customer->user->audits
                ->where('value', $payload->value)
                ->where('action', 'CREATED')
                ->where("type", "RANDOM_TRANSACTION");

            if (isset($payload->parent_reference_id)) {
                $audit->where("parent_reference_id", $payload->parent_reference_id);
            }

            $audit = $audit->first();

            if (!empty($audit)) {
                $t = new Transaction(($audit->service_id ?? $audit->reference_id), true);
                $t->voidfull();
            } else {
                return Responser::response(array('log' => 'Dados não conferem', 'message' => 'Dados não conferem'), Responser::PRECONDITION_FAILED);
            }

            $audit->action = $request->action ?? "VERIFIED";
            $audit->save();

            return ['status' => true];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function export(Request $request)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $domain = currentDomain(true);
        if ($domain->hasFeature('export_customer')) {
            $payload = $this->payload($request);
            $filename = "clientes_" . Carbon::now()->format('Y-m-d');
            return (new ExportCustomer($payload))->download($filename . ".xlsx", \Maatwebsite\Excel\Excel::XLSX);
        }
        throw new \Exception('Domínio não habilitado.');
    }

    public function searchFace(Request $request, searchFace $face)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $rules = [];
        if ($request->type == "file") {
            $rules["photo"] = "required|image";
        } elseif ($request->type == "url") {
            $rules["photo_url"] = "required|url";
        }

        $this->validate($request, $rules);
        return $face->execute($request);
    }

    public function activateClient(Request $request, $id, ActivateCustomer $activateCustomer)
    {
        $user = self::getUserAuth($request, true);
        if (is_null($user)) {
            return response()->json(['message' => 'Token inválido'], 406);
        }
        return $activateCustomer->execute($id, $user);
    }

    public function inWhitelist(Request $request, $id)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $customer = Customer::query()->findOrFail($id);

        $app = DB::table('app_identifiers')->where('user_id', $customer->user_id)->first();
        throw_if(empty($app), new \Exception('App identifier não encontrado'));

        return response()->json(['in_whitelist' => $app->whitelist]);
    }

    public function addWhitelist(Request $request, $id)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        $customer = Customer::withoutGlobalScope(DomainScope::class)->findOrFail($id);

        $app = DB::table('app_identifiers')->where('user_id', $customer->user_id)->first();
        throw_if(empty($app), new \Exception('App identifier não encontrado'));
        DB::table('app_identifiers')->where('user_id', $customer->user_id)->update(['whitelist' => 1]);

        return response()->json(['in_whitelist' => $app->whitelist]);
    }

    public function domains(Request $request)
    {
        $result = self::getUserAuth($request);
        if (is_null($result)) {
            return response()->json(['message' => 'Token inválido'], 426);
        }
        return Domain::identic('id', $request->id)->get()->toArray();
    }
}
