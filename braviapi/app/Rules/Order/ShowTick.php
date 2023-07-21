<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-24
 * Time: 15:14
 */

namespace App\Rules\Order;

class ShowTick
{
    public function execute($order)
    {
        try {
            if ($order->entrega) {
                $entrega = $order->entrega;

                if ($order->estado == 'F' || $order->estado == 'C') {
                    // pegar ticks do banco para pedidos finalizados ou cancelados
                    $recebimentos = $entrega
                        ->notificacoes_solicitacoes()
                        ->where('tipo', 'PEDIDO')
                        ->where('estado', 'RECEBEU')
                        ->exists();
                    $visualizacoes = $entrega
                        ->notificacoes_solicitacoes()
                        ->where('tipo', 'PEDIDO')
                        ->where('estado', 'VISUALIZOU')
                        ->exists();

                } else {
                    $logs = \RedisManager::lrange("PEDIDO.{$entrega->id}.confirmacaosolicitacao", 0, -1);
                    $recebimentos = false;
                    $visualizacoes = false;
                    if (isset($logs)) {
                        foreach ($logs as $log) {
                            $log = explode(";", $log);
                            $recebimentos = ($log[4] == 'RECEBEU') || $recebimentos;
                            $visualizacoes = ($log[4] == 'VISUALIZOU') || $visualizacoes;
                            if ($recebimentos && $visualizacoes) {
                                break;
                            }
                        }
                    }
                }

                if ($recebimentos && $visualizacoes) {
                    return asset('img/double-tick.png');
                } else if ($recebimentos) {
                    return asset('img/tick.png');
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}