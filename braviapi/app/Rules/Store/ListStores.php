<?php

namespace App\Rules\Store;

use Packk\Core\Models\Store;
use Packk\Core\Util\Phones;

class ListStores
{
    public function execute($request)
    {
        $perPage = $request->get('length', 10);
        $franchise = $request->have_franchise === 1 || $request->type === 'franchise';
        $notFranchise = $request->have_franchise === 2 || $request->type === 'normal';

        $query = Store::query()
            ->like('nome', $request->name)
            ->identic('lojas.id', $request->id)
            ->identic('cnpj', $request->cnpj)
            ->identic('lojista_id', $request->shopkeeper_id)
            ->identic('status', $request->status)
            ->identic('habilitado', $request->habilitated)
            ->identic('ativo', $request->active)
            ->identic('franchise_id', $request->franchise_id)
            ->identic('enderecos.cidade', $request->city)
            ->when(!empty($request->city), function ($q) use($request) {
                return $q->leftJoin('enderecos', 'enderecos.loja_id', 'lojas.id')
                    ->identic('enderecos.cidade', $request->city);
            })
            ->when($request->type === 'market', function ($q) {
                return $q->where('reference_provider', 'AMERICANAS_MARKET');
            })
            ->when($request->type === 'normal', function ($q) {
                return $q->whereNull('reference_provider');
            })
            ->when($franchise, function ($q) {
                return $q->whereNotNull('franchise_id');
            })
            ->when($notFranchise, function ($q) {
                return $q->whereNull('franchise_id');
            });

        if ((int)$request->get('page', 1) === 1) {
            $storesIds = $query->select('lojas.id')->get()->pluck('id')->toArray();
            $query = Store::query()->whereIn('lojas.id', $storesIds);
            $total = count($storesIds);
        }

        $data = $query
            ->with(['domain' => function ($q) {
                $q->select(['id', 'title']);
            }])
            ->with(['franchise' => function ($q) {
                $q->select(['id', 'name']);
            }])
            ->with(['address' => function ($q) {
                $q->select(['cidade as city', 'state', 'loja_id']);
            }])
            ->with(['shopkeeper' => function ($q) {
                $q->select(['id', 'email_proprietario as email']);
            }])
            ->select([
                'lojas.id',
                'lojas.nome as name',
                'lojas.habilitado as habilitated',
                'lojas.habilitado',
                'lojas.status',
                'lojas.type',
                'lojas.cnpj',
                'lojas.telefone as phone',
                'lojas.domain_id',
                'lojas.lojista_id',
                'lojas.franchise_id',
                'lojas.zoop_seller_id',
            ])
            ->orderByDesc('lojas.id')
            ->simplePaginate($perPage);

        $response = $data->toArray();
        if (isset($total)) {
            $response['total'] = $total;
        }

        foreach ($data->items() as $key => $item) {
            $response['data'][$key]['phone'] = empty($item->phone) ? null : Phones::formatExibe($item->phone);
            $response['data'][$key]['habilitated'] = boolval($item->habilitated);
        }

        return $response;
    }
}