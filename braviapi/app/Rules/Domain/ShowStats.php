<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-12-03
 * Time: 18:30
 */

namespace App\Rules\Domain;

use Packk\Core\Models\Store;
use Packk\Core\Models\Order;
use Packk\Core\Models\Customer;
use Packk\Core\Models\ProductSold;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShowStats
{
    public function execute($loja, $start_period, $end_period)
    {
        // return $this->mock();
        $start_date = Carbon::createFromFormat('d/m/Y H:i:s', $start_period . " 00:00:00");
        $end_date = Carbon::createFromFormat('d/m/Y H:i:s', $end_period . " 23:59:59");

        $pedidos_finalizados = Order
            ::on('replica')
            ->select('id', 'valor', DB::raw('SUBSTR(created_at, 1, 10) created_day'))
            ->where('estado', 'F')
            ->whereBetween('created_at', [$start_date, $end_date]);


        if ($loja != '') {
            try {
                $loja_id = Store::on('replica')->select('id')->where('nome', $loja)->firstOrFail()->id;
                $pedidos_finalizados = $pedidos_finalizados->where('loja_id', $loja_id);
            } catch (\Exception $e) {
                $loja_id = -1;
            }
        } else {
            $loja_id = -1;
        }

        $pedidos_finalizados = $pedidos_finalizados->with('produtos_vendidos')->get();

        $clientes = Customer
            ::on('replica')
            ->select('id', DB::raw('SUBSTR(created_at, 1, 10) created_day'))
            ->whereBetween('created_at', [$start_date, $end_date])
            ->get();

        $mais_vendidos = ProductSold
            ::on('replica')
            ->select('nome', DB::raw('sum(quantidade) quantidade'), DB::raw('sum(preco*quantidade) faturamento'))
            ->join('pedidos', 'pedidos.id', '=', 'produtos_vendidos.pedido_id')
            ->where('pedidos.estado', 'F')
            ->whereBetween('pedidos.created_at', [$start_date, $end_date]);

        if ($loja_id != -1) {
            $mais_vendidos = $mais_vendidos->where('loja_id', $loja_id);
        }

        $mais_vendidos = $mais_vendidos->groupBy('nome')
            ->orderByDesc(DB::raw('sum(quantidade)'))
            ->limit(10)
            ->get();

        $num_pedidos = $pedidos_finalizados->count();
        $num_clientes = $clientes->count();
        $pedidos_por_cliente = $num_clientes > 0 ? $num_pedidos / $num_clientes : 0;
        $ticket = $num_pedidos > 0 ? $pedidos_finalizados->sum('valor') / $num_pedidos : 0;
        $faturamento = $pedidos_finalizados->sum('valor');

        # novos clientes
        $start = $start_date->copy();
        $end = $end_date->copy();
        $novos_clientes = collect([]);
        do {
            $datekey = $start->toDateString();
            [$thisday, $clientes] = $clientes->partition(function ($c) use ($datekey) {
                return $c['created_day'] == $datekey;
            });
            $novos_clientes[$datekey] = $thisday;
            $start->addDay();
        } while ($start < $end);

        # faturamento bruto
        $start = $start_date->copy();
        $faturamento_bruto = collect([]);
        do {
            $datekey = $start->toDateString();
            [$thisday, $pedidos_finalizados] = $pedidos_finalizados->partition(function ($c) use ($datekey) {
                return $c['created_day'] == $datekey;
            });
            $faturamento_bruto[$datekey] = $thisday;
            $start->addDay();
        } while ($start < $end);

        return [
            'ticket' => $ticket,
            'num_pedidos' => $num_pedidos,
            'num_clientes' => $num_clientes,
            'pedidos_por_cliente' => $pedidos_por_cliente,
            'mais_vendidos' => $mais_vendidos,
            'novos_clientes' => [
                'labels' => $novos_clientes->keys()->toArray(),
                'data' => implode(',', $novos_clientes->map(function ($c) {
                    return $c->count();
                })->toArray())
            ],
            'faturamento_bruto' => [
                'labels' => $faturamento_bruto->keys()->toArray(),
                'data' => implode(',', $faturamento_bruto->map(function ($p) {
                    return $p->sum('valor') / 100;
                })->toArray())
            ],
            'faturamento' => $faturamento
        ];
    }

    public function mock()
    {
        return [
            'ticket' => 4300,
            'num_pedidos' => 1000,
            'num_clientes' => 30,
            'pedidos_por_cliente' => 1,
            'mais_vendidos' => [
                [
                    "nome" => "Irineu",
                    "quantidade:" => "34",
                    "faturamento" => 510,
                ],
                [
                    "nome" => "Pericão",
                    "quantidade:" => "34",
                    "faturamento" => 250,
                ]
            ],
            'novos_clientes' => [
                'labels' => [
                    "2022-01-15", "2022-01-16", "2022-01-17", "2022-01-18", "2022-01-19", "2022-01-20", "2022-01-21", "2022-01-22", "2022-01-23", "2022-01-24",
                    "2022-01-25", "2022-01-26", "2022-01-27", "2022-01-28", "2022-01-29", "2022-01-30", "2022-01-31", "2022-02-01", "2022-02-02", "2022-02-03",
                    "2022-02-04", "2022-02-05", "2022-02-06", "2022-02-07", "2022-02-08", "2022-02-09", "2022-02-10", "2022-02-11", "2022-02-12", "2022-02-13", "2022-02-14",
                ],
                'data' => "0,0,0,0,0,0,0,0,0,0,3,0,0,0,0,0,1,0,1,0,0,0,0,0,0,0,2,1,0,0,0",
            ],
            'faturamento_bruto' => [
                'labels' => [
                    "2022-01-15", "2022-01-16", "2022-01-17", "2022-01-18", "2022-01-19", "2022-01-20", "2022-01-21", "2022-01-22", "2022-01-23", "2022-01-24",
                    "2022-01-25", "2022-01-26", "2022-01-27", "2022-01-28", "2022-01-29", "2022-01-30", "2022-01-31", "2022-02-01", "2022-02-02", "2022-02-03",
                    "2022-02-04", "2022-02-05", "2022-02-06", "2022-02-07", "2022-02-08", "2022-02-09", "2022-02-10", "2022-02-11", "2022-02-12", "2022-02-13", "2022-02-14",
                ],
                'data' => "0,0,0,0,0,0,0,0,0,0,0,11,75,0,0,0,30,15,10,140,60,0,0,45,45,0,220,120,0,0,0"
            ],
            'faturamento' => 1000
        ];
    }
}