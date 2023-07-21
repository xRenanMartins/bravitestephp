<?php

namespace App\Rules\Order;

use Packk\Core\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use DB;
class ListOrders
{
    public function execute(Request $payload)
    {
        $newOrder = new Order();
        $newOrder->setConnection('lake');

        $query = $newOrder
            ->join('enderecos', 'enderecos.loja_id', '=', 'pedidos.loja_id')
            ->join('lojas', 'lojas.id', '=', 'pedidos.loja_id')
            ->with('customer', function($q) {
                $q->withTrashed()->with('user', function($q) {
                    $q->withTrashed();
                });
            })->with('store', function($q) {
                $q->withTrashed();
            })->with('products_sold')
            ->selectRaw('pedidos.id, pedidos.created_at, pedidos.taxa_entrega_cliente, pedidos.creditos_payout, pedidos.voucher_payout, pedidos.estado')
            ->selectRaw('pedidos.cliente_id, pedidos.loja_id, pedidos.valor, pedidos.modo_entrega, enderecos.cidade, enderecos.state')
            ->orderByDesc('pedidos.id');

        // Para franqueados
        $user = Auth::user();
        if ($user->isFranchiseOperator()) {
            $franchise = $user->getFranchise();

            if (!empty($franchise)) {
                $query->whereHas('store', function ($query) use ($franchise) {
                    $query->where('franchise_id', $franchise->id);
                });
            } else {
                $query->whereHas('store', function ($query) {
                    $query->whereNotNull('franchise_id');
                });
            }
        }
        // Status
        if (!empty($payload->status)) {
            $query->where('pedidos.estado', $payload->status);
        }
        // Loja
        if (count($payload->get('loja_id', [])) > 0) {
            $query->whereIn('pedidos.loja_id', $payload['loja_id']);
        }
        // Franquia
        if (count($payload->get('franchise_id', [])) > 0) {
            $query->whereIn('franchise_id', $payload['franchise_id']);
        }
        // Categorias
        if (count($payload->get('categories', [])) > 0) {
            $query->join('categoria_loja', 'categoria_loja.loja_id', '=', 'lojas.id')
                ->whereIn('categoria_id', $payload['categories']);
        }

        // Data e horário
        if (!empty($payload->range)) {
            $range = explode(',', $payload['range']);
            try {
                $rangePT = $range;
                $range[0] = $range[0].$range[1];
                $range[3] = $range[2].$range[3];
                $startDate = Carbon::createFromFormat('m/d/Y H:i:s A', Arr::first($range));
                $endDate = Carbon::createFromFormat('m/d/Y H:i:s A', Arr::last($range));
            } catch (\Throwable $th) {
                $range = $rangePT;
                $startDate = Carbon::createFromFormat('d/m/Y H:i:s', Arr::first($range));
                $endDate = Carbon::createFromFormat('d/m/Y H:i:s', Arr::last($range));
            }
            
            $query->whereBetween('pedidos.created_at', [$startDate, $endDate]);
        }
        if($payload->export){
            $result = $query->simplePaginate(1000);
        }else{
            $result = $query->simplePaginate($payload->get('length', 15));
        }
        return response()->json($result);
    }
    public function export(Request $payload){
        $payload->merge(['export' => true]);
        return $this->execute($payload);
    }
}