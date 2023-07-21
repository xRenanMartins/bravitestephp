<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-24
 * Time: 14:58
 */

namespace App\Rules\Concierge;

use Packk\Core\Models\Order;
use Packk\Core\Integration\Payment\Buyer;

class StoreCorrection
{
    public function execute($limit)
    {
        $map_pedidos = self::map();
        $pedidos = Order::where('tipo', 'CONCIERGE')
            ->where('created_at', '>', '2018-08-16 15:15:00')
            ->where('created_at', '<', '2018-08-21 09:00:00')
            ->where('zoop_transaction_id', null)
            ->whereNull('regiao')
            ->limit($limit)
            ->get();

        foreach ($pedidos as $pedido) {

            if (isset($map_pedidos[$pedido->id])) {

                $valor_ajustado = $map_pedidos[$pedido->id];
                if ($valor_ajustado < 0) {
                    continue;
                }

                $zoop_buyer = new Buyer($pedido->cliente->zoop_buyer_id);
                // $zoop_buyer->add_card($this->token_pagamento);
                try {

                    $transaction = $zoop_buyer->charge(
                        [
                            "amount" => $valor_ajustado,
                            "description" => "Pagamento pedido {$pedido->id}",
                            "on_behalf_of" => defaultSeller(),
                            "capture" => false,
                            "source_id" => $pedido->id,
                            "source_provider" => "pedido-shipp",
                        ]
                    );
                    if ($transaction != null) {
                        $pedido->zoop_transaction_id = $transaction->id;
                        $pedido->valor = $valor_ajustado;
                        $pedido->save();
                        echo 'pedido ' . $pedido->id . ' atualizado corretamente' . PHP_EOL;
                    }
                } catch (\Throwable $t) {
                    $pedido->regiao = 'ignorar';
                    $pedido->save();
                    echo 'erro no pedido ' . $pedido->id . PHP_EOL;
                }
            } else {
                $pedido->regiao = 'ignorar';
                $pedido->save();
                echo 'pedido ' . $pedido->id . ' não está mapeado' . PHP_EOL;
            }
        }
    }

    private function map()
    {

        $handle = fopen(config_path('cbf.csv'), "r");
        $header = true;
        $data = [];

        while ($csvLine = fgetcsv($handle, 1000, ";")) {
            if ($header) {
                $header = false;
            } else {
                if (!empty($csvLine[2])) {
                    $data[intval($csvLine[0])] = intval(str_replace([',', 'R$'], '', $csvLine[2]));
                }
            }
        }
        return $data;
    }
}