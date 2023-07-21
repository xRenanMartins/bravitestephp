<?php

namespace App\Rules\Store;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Integration\Skore\Skore;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Ban;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\Retention;
use Packk\Core\Models\Store;
use Packk\Core\Models\StoreActivity;
use Packk\Core\Models\UserFranchise;
use Packk\Core\Util\HttpResponser;

class Stores
{
    public static function index($request)
    {
        $request->length = isset($request->length) ? $request->length : 20;
        if (isset($request->start) and isset($request->length)) {
            $total = $request->start / $request->length;
            $page = ($total + 1) > 0 ? ceil($total) + 1 : 1;
            $request->merge([
                'page' => $page
            ]);
        }
        $storesNotIn = false;
        if (isset($request->stores_selected)) {
            $storesNotIn = explode(',', $request->stores_selected);
        }

        $query = Store::select('id', 'nome', 'cnpj')
            ->when($storesNotIn, function ($query, $storesNotIn) {
                return $query->whereNotIn("id", $storesNotIn);
            })
            ->orderBy('nome', 'asc');

        $user = Auth::user();
        $type = 'admin';

        $franchises = [];
        if (isset($user)) {
            if ($user->hasRole('admin-franchise|operator-franchise')) {
                $type = 'franchise';
                $franchises_ids = UserFranchise::query()
                    ->where('user_id', '=', $user['id'])
                    ->pluck('franchise_id');

                $query->whereIn('franchise_id', $franchises_ids)->with('franchise');
                $franchises = UserFranchise::query()
                    ->where('user_id', '=', $user['id'])
                    ->with('franchise')
                    ->get();
            }
            if ($user->hasRole('admin-franchise-all')) {
                $type = 'franchise';

                $franchises = UserFranchise::query()
                    ->with('franchise')
                    ->get();

                if (isset($request->franchise_id)) {
                    $query->where('franchise_id', $request->franchise_id);
                }

                $query->whereNotNull('franchise_id')->with('franchise');
            }
        }

        if ($request->paginate) {
            $data = $query->join('lojistas', 'lojistas.id', '=', 'lojas.lojista_id')
                ->join('users', 'users.id', '=', 'lojistas.user_id')
                ->join('enderecos', 'enderecos.loja_id', '=', 'lojas.id')
                ->join('domains', 'domains.id', '=', 'lojas.domain_id')
                ->like('lojas.nome', $request->nome)
                ->like('lojas.id', $request->id)
                ->like('lojas.habilitado', $request->habilitado)
                ->like('lojas.cpf', $request->cpf)
                ->identic('lojas.status', $request->status)
                ->identic('lojas.domain_id', $request->domain_id)
                ->select(DB::raw('
                            CONCAT(enderecos.cidade, " - ", enderecos.state) as local,
                            CONCAT(enderecos.endereco, ", ", enderecos.numero) as rua,
                            enderecos.bairro as bairro,
                            lojas.*,
                            users.nome as lojista_nome,
                            users.email as lojista_email,
                            users.telefone as lojista_fone,
                            domains.name as domain_desc
                        '))
                ->paginate($request->length);
            $response = [];
            foreach ($data->items() as $item) {
                $temp = $item->toArray();
                $temp['aberto'] = $item->esta_aberto() ? 1 : 0;
                if (!strstr($item->lojista_fone, '(')) {
                    if (strlen($item->lojista_fone) == 10) {
                        $new = substr_replace($item->lojista_fone, '(', 0, 0);
                        $new = substr_replace($new, '9', 3, 0);
                        $new = substr_replace($new, ')', 3, 0);
                        $item->lojista_fone = $new;
                        $temp['lojista_fone'] = $item->lojista_fone;
                    } else {
                        $new = substr_replace($item->lojista_fone, '(', 0, 0);
                        $new = substr_replace($new, ')', 3, 0);
                        $new = substr_replace($new, '-', 9, 0);
                        $new = substr_replace($new, ' ', 5, 0);
                        $new = substr_replace($new, ' ', 4, 0);
                        $item->lojista_fone = $new;
                        $temp['lojista_fone'] = $item->lojista_fone;
                    }
                }
                $response[] = $temp;
            }

            return [
                'type' => $type,
                'franchises' => $franchises,
                'data' => [
                    'data' => $response,
                    'total' => $data->total()
                ]
            ];
        } else {
            if (isset($request->ids_enabled) && $request->ids_enabled) {
                $query = $query->whereIn('id', explode(',', $request->ids))
                    ->where('habilitado', $request->habilitado)
                    ->identic('domain_id', $request->domain_id);

            } else if (!empty($request->ids)) {
                $query = $query->whereIn('id', explode(',', $request->ids))
                    ->orWhere(function ($q) use ($request) {
                        $q->like('nome', $request->nome)
                            ->identic('domain_id', $request->domain_id)
                            ->identic('habilitado', $request->habilitado);
                    });
            } else {
                $query = $query->like('nome', $request->nome)
                    ->identic('domain_id', $request->domain_id)
                    ->identic('lojas.status', $request->status)
                    ->identic('habilitado', $request->habilitado);
            }
            return [
                'type' => $type,
                'franchises' => $franchises,
                'data' => $query->paginate($request->length)
            ];
        }
    }

    public static function recess($request)
    {
        try {
            DB::beginTransaction();
            $domain = currentDomain(true);

            switch ($request->recesso_type) {
                case 'RECESS': //Closed all stores
                    $domain->setSetting('recess_type', $request->recesso_type);
                    break;
                case 'RECESS_STORES'://Disable Makeplaces
                    $domain->setSetting('recess_type', $request->recesso_type);
                    break;
                case 'RECESS_NORMAL_STORES'://Leave Open Marketplaces and Exclusives
                    $domain->setSetting('recess_type', $request->recesso_type);
                    break;
                case 'disable_recess': //Open all Stores
                    $domain->setSetting('recess_type', null);
                    break;
                default:
                    $domain->setSetting('recess_type', null);
                    break;
            }

            DB::commit();
            // post content to cloud

            $user = Auth::user();
            $domain_id = currentDomain();
            $timestamp = Carbon::now();

            $msg = "{$user->id},{$user->email},{$user->nome},{$user->sobrenome},{$timestamp},{$domain_id}";//enum

            $log = [
                'user_id' => $user->id,
                'email' => $user->email,
                'date' => $timestamp,
                'domain_id' => $domain_id,
                'provider' => 'CLOSED_ALL_STORES'
            ];

            DB::connection('utils')->table('mslgc_operations')->insert($log);
            return HttpResponser::response(['estado' => $request->recesso_type], HttpResponser::OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return HttpResponser::response(['e' => $e->getMessage()], HttpResponser::SERVER_ERROR);
        }
    }

    public static function habilitate($request)
    {
        $user = Auth::user();

        try {
            $loja = Store::findOrFail($request['store_id']);
            $loja->habilitado = !$loja->habilitado;
            $loja->save();

            $query = new StoreActivity();
            $query->user_id = $user['id'];
            $query->store_id = $request['store_id'];
            $query->description = $request['description'];
            $query->activity = 'HABILITAR';
            $query->reason_id = $request['reason_id'];

            if (!$loja->habilitado) {
                $query->activity = 'DESABILITAR';
                (new Skore($loja->id, false))->execute();
            } else {
                (new Skore($loja->id, true))->execute();
            }

            $query->save();
            dispatch(new SendShopFeedEvent($loja->id, 'rules:change', ['enabled']));
            dispatch(new SendShopFeedEvent($loja->id, 'store.update', ['is_enabled']));

            return HttpResponser::response(['habilitado' => $loja->habilitado], HttpResponser::OK);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public static function addRetention($request)
    {
        $retencao = new Retention();
        $data = Carbon::createFromFormat('d/m/Y', $request->comeca_em);
        $valor = str_replace('R$ ', '', $request->valor);
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '', $valor);
        $loja = Store::find($request->loja_id);;
        $retencao->valor = $valor;
        $retencao->estado = 'PENDENTE';
        $retencao->tipo = 'RETENCAO';
        $retencao->billing_type = 'RETENCAO_ADMIN';
        $retencao->descricao = $request->descricao;
        $retencao->comeca_em = $data;
        $retencao->periodicidade = 1;
        $retencao->parcelas = 1;
        $retencao->domain_id = $loja->domain_id;
        $retencao->loja_id = $request->loja_id;
        $retencao->save();

        $loja->wallet->add([
            'type' => 'RETENTION',
            'amount' => $retencao->valor,
            'status' => 'HOLD',
            'reference' => $retencao,
            'active_after' => $retencao->comeca_em,
            'description' => $retencao->descricao
        ]);
        return [true];
    }

    public static function BanDeliveryman($payload)
    {
        if ($payload['entregadores_ids']) {
            $ids = str_replace(' ', '', $payload['entregadores_ids']);
            $ids = explode(',', $ids);
        } else {
            $ids = [];
        }

        $loja = Store::find($payload['loja_id']);

        DB::beginTransaction();
        try {
            if (count($ids) == 0) {
                $banidos = DB::select('select * from banimentos where loja_id = ?', [$payload['loja_id']]);
                foreach ($banidos as $key => $b) {
                    $this->unBanShipperStoreAtividade($loja, $b->entregador_id);
                }
                $loja->banimentos()->sync($ids);
            } else {
                $banidos = Ban::getBanidos($payload['loja_id']);
                foreach ($ids as $key => $id) {
                    if (!in_array($id, $banidos)) {
                        $this->banShipperStoreAtividade($loja, $id);
                    }
                }
                // Verifica se algum entregador foi removido
                foreach ($banidos as $key => $b) {
                    if (!in_array($b, $ids)) {
                        $this->unBanShipperStoreAtividade($loja, $b);
                    }
                }
                $loja->banimentos()->sync($ids);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
        // Limpando cache de banimentos
        Cache::forget('banimentos');
        return [true];
    }

    public static function unBanShipperStoreAtividade(Store $loja, int $entregador_id)
    {
        $entregador = Deliveryman::find($entregador_id);
        $context = [
            '[::operador]' => Auth::user()->nome . ' ' . Auth::user()->sobrenome,
            '[::shipper]' => "{$entregador->user->nome} {$entregador->user->sobrenome}",
            '[::loja]' => $loja->nome
        ];

        $entregador->user->addAtividade('ADMIN_STORE_UNBAN_SHIPPER', $context, Auth::user()->id, 'ADMIN');
    }

    public static function banShipperStoreAtividade(Store $loja, int $entregador_id)
    {
        $entregador = Deliveryman::find($entregador_id);
        $context = [
            '[::operador]' => Auth::user()->nome . ' ' . Auth::user()->sobrenome,
            '[::shipper]' => "{$entregador->user->nome} {$entregador->user->sobrenome}",
            '[::loja]' => $loja->nome
        ];

        $entregador->user->addAtividade('ADMIN_STORE_BAN_SHIPPER', $context, Auth::user()->id, 'ADMIN');
    }
}