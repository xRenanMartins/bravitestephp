<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-24
 * Time: 15:11
 */

namespace App\Rules\Order;

use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\NotificationRequest;
use Carbon\Carbon;

class ShowTickDetail
{
    public function execute($order)
    {
        try {
            if ($order->entrega) {
                $entrega = $order->entrega;
                if ($order->estado == 'F' || $order->estado == 'C') {
                    // pegar ticks do banco para pedidos finalizados ou cancelados
                    $ls = $entrega->notificacoes_solicitacoes;

                } else {
                    $logs = \RedisManager::lrange("PEDIDO.{$entrega->id}.confirmacaosolicitacao", 0, -1);
                    $ls = collect([]);
                    if (isset($logs)) {
                        foreach ($logs as $log) {
                            $log = explode(";", $log);
                            $ns = new NotificationRequest();
                            $ns->entrega_id = $entrega->id;
                            $ns->entregador_id = $log[0];
                            $ns->latitude = $log[1];
                            $ns->longitude = $log[2];
                            $ns->created_at = new Carbon($log[3]);
                            $ns->estado = $log[4];
                            $ns->tipo = $log[5];
                            $ls->push($ns);
                        }
                    }
                }
                $retorno = collect([]);
                foreach ($ls as $l) {
                    if (!isset($retorno[$l->entregador_id])) {

                        $e = Deliveryman::find($l->entregador_id);
                        $o = (object)[];
                        $o->id = $e->id;
                        $o->nome = $e->user->nome_completo;
                        $o->telefone = $e->user->telefone;
                        $o->recebidos = 0;
                        $o->visualizados = 0;

                        $retorno[$l->entregador_id] = $o;
                    }

                    $o = $retorno[$l->entregador_id];
                    if ($l->estado == 'RECEBEU') {
                        $o->recebidos += 1;
                    } else {
                        $o->visualizados += 1;
                    }
                }
                return $retorno->values();
            } else {
                return collect([]);
            }
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}