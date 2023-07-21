<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Rules\Voucher\CreateVoucher;
use App\Rules\Voucher\UpdateVoucher;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\CustomerGroup;
use Packk\Core\Models\Store;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Voucher;
use Packk\Core\Models\FirebaseTopic;
use Packk\Core\Models\AddressServed;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = Voucher::query();

        $query->like('chave', $request->key)
            ->when(!empty($request->region), function ($query) use ($request) {
                $query->whereHas('firebase_topics', function ($query) use ($request) {
                    $query->where('firebase_topics.id', $request->region);
                });
            })->orderByDesc('created_at');
        if (!empty($request->active)) {
            if (intval($request->active) == 0) {
                $query->whereNotNull('expired_by');
            } else {
                $query->whereNull('expired_by');
            }
        }

        if (!empty($request->start)) {
            $query = $query->where('inicio', '>=', $request->start);
        }
        if (!empty($request->validate)) {
            $query = $query->where('validade', '<=', $request->validate);
        }

        $data = $query->simplePaginate($request->length);
        $response = $data->toArray();
        foreach ($data->items() as $key => $item) {
            $response['data'][$key]['expirado'] = $item->expired();
            $response['data'][$key]['regioes'] = $item->getRegioes();
            if (!sizeof($item->orders()->where('estado', '!=', 'C')->get()) >= $item->quantidade_total && $response['data'][$key]['expired_by'] == null) {
                $response['data'][$key]['expirado'] = false;
            }
        }

        return $response;
    }

    public function edit(Request $request, $id)
    {
        $voucher = Voucher::find($id);
        $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $intevaloDia = 48;
        $intevaloHora = 2;
        $intevaloMinuto = 30;
        $horario = $voucher->time;
        $string = $horario;
        $horarios = explode("\r\n", chunk_split($string, $intevaloDia));
        $resp = $voucher->toArray();
        $resp['breaks'] = [];
        for ($i = 0; $i < sizeof($dias); $i++) {
            $breaks = collect([]);
            $ini = -1;
            $fim = -1;
            $index = '1232';
            if (isset($string) && strlen($string) > 0) {
                for ($j = 0; $j < $intevaloDia; $j++) {
                    $index = $horarios[$i];
                    if ($horarios[$i][$j] == "1" && $ini == -1) {
                        $ini = $j;
                    }
                    if (($horarios[$i][$j] == "0" && $ini != -1) || ($ini != -1 && $j == $intevaloDia - 1)) {
                        $intervalo = new \stdClass();
                        $valueHourIni = $ini / $intevaloHora;
                        $valueMinuteIni = $ini % $intevaloHora;
                        $valueHourFim = $j / $intevaloHora;
                        $valueMinuteFim = $j % $intevaloHora;

                        if ($index[$intevaloDia - 1] == 1) {
                            $valueHourFim += 1;
                            $valueMinuteFim = 0;
                        }

                        $intervalo->inicio = sprintf('%02d', $valueHourIni) . ":" . ($valueMinuteIni == 0 ? "00" : $valueMinuteIni * $intevaloMinuto);
                        $intervalo->fim = sprintf("%02d", $valueHourFim) . ":" . ($valueMinuteFim == 0 ? "00" : ($valueMinuteFim) * $intevaloMinuto);
                        $breaks->push($intervalo);
                        $fim = -1;
                        $ini = -1;
                    }
                }
            }

            $resp['breaks'][$i] = [
                'dia' => $dias[$i],
                'horario' => $breaks
            ];
        }

        $blacklist = [];
        $blacklist['store'] = $voucher->stores()->where('blacklist', true)->exists();
        $blacklist['customer'] = $voucher->customers()->where('blacklist', true)->exists();
        $blacklist['product'] = $voucher->products()->where('blacklist', true)->exists();

        $data = [
            'breaks' => $resp['breaks'],
            'blacklist' => $blacklist
        ];

        return response()->json($data);
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        if ($request->with_stores) {
            $lojasQuery = Store::query()->select('id', 'nome')
                ->orderBy('nome');

            if ($user->isFranchiseOperator()) {
                $lojasQuery->whereNotNull('franchise_id');

                $franchise = $user->getFranchise();
                if (!empty($franchise)) {
                    $lojasQuery->where('franchise_id', $franchise->id);
                } elseif (!empty($request->franchise_id)) {
                    $lojasQuery->where('franchise_id', $request->franchise_id);
                }
            }

            return response([
                'success' => true,
                'lojas' => $lojasQuery->get()
            ]);
        }

        $customerGroups = CustomerGroup::query()->select('id', 'title')
            ->orderBy('title')->get();

        $regions = FirebaseTopic::query()
            ->leftJoin('franchises', 'franchises.firebase_topic_id', 'firebase_topics.id')
            ->where(function ($query) {
                $query->whereNull('franchises.id')->orWhere('franchises.active', 0);
            })->whereIn('type', ['CLIENTE', 'FRANQUIA'])
            ->selectRaw("IF(type = 'FRANQUIA', CONCAT(firebase_topics.type, ' ', firebase_topics.name), firebase_topics.name) as name")
            ->selectRaw('firebase_topics.id')->get();

        return response([
            'success' => true,
            'regioes' => $regions,
            'customerGroups' => $customerGroups
        ]);
    }

    public function regioes(Request $request)
    {
        return AddressServed::query()->selectRaw('distinct cidade, cidade as name')->get();
    }

    public function store(Request $request)
    {
        $voucher = new CreateVoucher();
        $payload = $this->payload($request);
        $voucher->execute($payload);

        return ApiResponse::sendResponse();
    }

    public function update(Request $request, $id)
    {
        $voucher = new UpdateVoucher();
        $payload = $this->payload($request);
        $voucher->execute($payload, $id);

        return ApiResponse::sendResponse();
    }

    public function expire(Request $request, $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $voucher->validade = \Carbon\Carbon::now()->subMinutes(5);
            $voucher->expired_by = Auth::user()->email;
            $voucher->save();

            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Voucher não encontrado!');
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function storesToTarget(Request $request, $id)
    {
        $stores = Store::query()
            ->join('loja_voucher', 'loja_id', '=', 'lojas.id')
            ->where('loja_voucher.voucher_id', $id)
            ->selectRaw('lojas.id, lojas.nome');

        if (!empty($request->outside)) {
            $stores->whereNotIn('lojas.id', explode(',', $request->outside));
        }

        if (!empty($request->search)) {
            $stores->where(function ($query) use ($request) {
                $query->where('nome', 'like', "%{$request->search}%")
                    ->orWhere('lojas.id', $request->search);
            });
        }

        return $stores->groupBy('id')->simplePaginate($request->length);
    }

    public function storesToSource(Request $request, $id)
    {
        $stores = Store::query()->selectRaw('id, nome, cnpj');

        if ($id > 0) {
            $vouchers = DB::table('loja_voucher')->where('voucher_id', $id)
                ->get()->pluck('loja_id')->toArray();
            $stores->whereNotIn('id', $vouchers);
        }

        if (!empty($request->outside)) {
            $stores->whereNotIn('id', explode(',', $request->outside));
        }

        if (!empty($request->search)) {
            $stores->where(function ($query) use ($request) {
                $query->where('nome', 'like', "%{$request->search}%")
                    ->orWhere('id', $request->search);
            });
        }

        $user = Auth::user();
        if (isset($user) && $user->isFranchiseOperator()) {
            $franchise = $user->getFranchise();
            if (!empty($franchise)) {
                $stores->where('franchise_id', $franchise->id);
            } else {
                $stores->whereNotNull('franchise_id');
            }
        }

        return $stores->orderBy('nome')->simplePaginate($request->length);
    }

    public function customersToTarget(Request $request, $id)
    {
        $customers = Customer::query()
            ->join('voucher_customer', 'cliente_id', '=', 'clientes.id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->where('voucher_customer.voucher_id', $id)
            ->selectRaw('clientes.id, concat(users.nome, " ", users.sobrenome) as nome ');

        if (!empty($request->outside)) {
            $customers->whereNotIn('clientes.id', explode(',', $request->outside));
        }

        if (!empty($request->search)) {
            $customers->where(function ($query) use ($request) {
                $query->where('nome', 'like', "%{$request->search}%")
                    ->orWhere('clientes.id', $request->search);
            });
        }

        return $customers->groupBy('clientes.id')->simplePaginate($request->length);
    }

    public function customersToSource(Request $request, $id)
    {
        $customers = Customer::join('users', 'users.id', '=', 'clientes.user_id')
            ->selectRaw('clientes.id, concat(users.nome, " ", users.sobrenome) as nome, users.cpf');

        if ($id > 0) {
            $vouchers = DB::table('voucher_customer')->where('voucher_id', $id)
                ->get()->pluck('cliente_id')->toArray();
            $customers->whereNotIn('clientes.id', $vouchers);
        }

        if (!empty($request->outside)) {
            $customers->whereNotIn('clientes.id', explode(',', $request->outside));
        }

        if (!empty($request->search)) {
            $customers->where(function ($query) use ($request) {
                $query->where('users.nome', 'like', "%{$request->search}%")
                    ->orWhere('clientes.id', $request->search);
            });
        }

        $user = Auth::user();
        if (isset($user) && $user->isFranchiseOperator()) {
            $franchise = $user->getFranchise();
            if (!empty($franchise)) {
                $customers->where('franchise_id', $franchise->id);
            } else {
                $customers->whereNotNull('franchise_id');
            }
        }

        return $customers->orderBy('users.nome')->simplePaginate($request->length);
    }
}
