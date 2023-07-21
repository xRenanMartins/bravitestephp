<?php

namespace App\Rules\Franchise;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Store;

class TransfersResume
{
    public function execute($payload)
    {
        $query = DB::table('pedidos')
            ->join('lojas', 'lojas.id', '=', 'pedidos.loja_id')
            ->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'pedidos.payment_method_id')
            ->join('franchises', 'franchises.id', '=', 'lojas.franchise_id')
            ->join('statements', function ($q) {
                $q->on('statements.reference_id', '=', 'pedidos.id')
                    ->where('statements.status', '<>', 'CANCELLED')
                    ->where('statements.reference_provider', 'App\Models\Pedido');
            })->join('wallets', function ($q) {
                $q->on('wallets.id', '=', 'statements.wallet_id')
                    ->where('wallets.owner_type', 'App\Models\Franchise');
            });
        $queryTotals = clone $query;
        $query->select([
            'pedidos.id',
            "lojas.franchise_id",
            "franchises.name as franchise",
            "pedidos.created_at",
            "lojas.id as store_id",
            "lojas.nome as store_name",
            "pedidos.valor as value",
            "pedidos.metodo_pagamento",
            "payment_methods.mode",
            "payment_methods.name as payment_method",
            "statements.amount as franchise_value",
        ])
            ->selectRaw("IF(pedidos.modo_entrega in ('TAKEOUT', 'TAKEOUT_LOCAL', 'TAKEOUT_MARKETPLACE', 'TAKEOUT_LOCAL_LOCKER'), 'TAKEOUT','DELIVERY') as delivery_method")
            ->selectRaw("(pedidos.creditos_payout + pedidos.voucher_payout + pedidos.takeout_payout + pedidos.taxa_entrega_payout + pedidos.office_payout) as incentives")
            ->selectRaw("concat(users.nome, ' ', users.sobrenome) as client_name");
        // Data e horário
        if (!empty($payload->range)) {
            $range = explode(',', $payload->range);
            $startDate = Carbon::createFromFormat('d/m/Y H:i:s', Arr::first($range));
            $endDate = Carbon::createFromFormat('d/m/Y H:i:s', Arr::last($range));
            $query->whereBetween('pedidos.created_at', [$startDate, $endDate]);
        }
        // Lojas
        if (!empty($payload->stores)) {
            $query->whereIn('lojas.id', explode(',', $payload->stores));
        }
        // Franquias
        if (!empty($payload->franchises)) {
            $query->whereIn('lojas.franchise_id', explode(', ', $payload->franchises));
        }
        $result = $query->orderByDesc('id')->paginate($payload->length);
        $response = $result->toArray();
        foreach ($result->items() as $key => $item) {
            $response['data'][$key]->value = $item->value / 100;
            $response['data'][$key]->incentives = $item->incentives / 100;
            $response['data'][$key]->franchise_value = $item->franchise_value / 100;
            $store = Store::find($item->store_id);
            $commission = $store->commission($item->metodo_pagamento);
            $valueShopkeeper = round((1.0 - ($commission / 100.0)) * $item->value);
            $response['data'][$key]->seller_value = $valueShopkeeper;
            $response['data'][$key]->commission = $commission;
            $response['data'][$key]->adjusted_commission = $item->mode === 'ONLINE' ? $commission - 2.3 : $commission;
        }
        if (!isset($payload->page) || $payload->page === 1) {
            $response['franchise_value_total'] = $queryTotals->selectRaw('statements.amount')
                    ->sum('statements.amount') / 100;
        }
        return $response;
    }
}