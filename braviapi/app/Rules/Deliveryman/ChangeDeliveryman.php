<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-08-04
 * Time: 15:39
 */

namespace App\Rules\Deliveryman;

use Packk\Core\Jobs\NewDispatcher\Rules\BlockedFromTakingCashOrder;
use Packk\Core\Actions\Admin\Delivery\AcceptRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\Order;
use Packk\Core\Models\Service;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\Property;
use Packk\Core\Models\Ban;

class ChangeDeliveryman
{
    public function delivery($payload)
    {
        try {
            $pedido = Order::findOrFail($payload->pedido_id);
            $entregador_novo = Deliveryman::findOrFail($payload->entregador_id);
            $entregador_antigo = $pedido->entrega->entregador;
            $deliverymanQualifiedForDelivery = false;
            $banidoLoja = Ban::isBan($pedido->loja->id, $payload->entregador_id);
            $rule = new AcceptRule();
            $orderWithDelivery = false;
            if ($banidoLoja) {
                throw new RuleException("", "Entregador Banido desta loja", 430);
            }

            if ($pedido->entrega) {
                $orderWithDelivery = true;
                DB::beginTransaction();
                try {
                    if (isset($pedido->entrega->entregador) && Property::get('BPP_AUTO_CHARGE') == '1') {
                        if (isset($pedido->entrega->entregador->pre_paid_card)) {
                            $card = $pedido->entrega->entregador->pre_paid_card;
                            $balance = $card->balance();
                            if ($balance > 0) {
                                $card->debit($balance, $pedido->entrega->entregador->id);
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
                if (isset($pedido->entrega->entregador)) {
                    $pedido->entrega->entregador->push_reload();
                }

                $pedido->em_espera = 0;
                $pedido->entrega->cartao_recarregado = false;
                $pedido->entrega->save();
                DB::commit();
            }

            $payload = [
                "entrega_id" => $pedido->entrega->id,
                "entregador_id" => $payload->entregador_id,
                "latitude" => null,
                "longitude" => null,
                "admin" => true
            ];

            $acceptRule = json_decode($rule->execute($payload)->getContent());
            if (isset($acceptRule->error)) {
                throw new RuleException($acceptRule->log, $acceptRule->error, 430);
            }

            if ($pedido->metodo_pagamento == "DINHEIRO") {
                $deliverymanQualifiedForDelivery = $this->deliverymanQualified($pedido, $entregador_novo);
            }

            if ($orderWithDelivery) {
                $pedido->atualizaPusher();
                $entregador_novo->push_reload();
            }
            $deliverimanOnDelivery = $acceptRule->on_deliveries ?? "";
            $this->logDeliverymanChange($entregador_antigo, $entregador_novo, $pedido);
            return ['log' => $pedido->entrega, 'deliverimanOnDelivery' => $deliverimanOnDelivery, 'deliverymanQualified' => $deliverymanQualifiedForDelivery];
        } catch (\Exception $e) {
            DB::rollback();
            if ($e->getCode() == 0)
                throw new \Exception("Ocorreu um erro ao trocar o entregador", 0, $e);
            else
                throw $e;
        }
    }

    public function service($payload)
    {
        try {
            $favor = Service::findOrFail($payload->favor_id);
            $entregador_novo = Deliveryman::findOrFail($payload->entregador_id);

            DB::beginTransaction();

            if (isset($favor->entregador)) {
                $entregador_antigo = $favor->entregador;
            }

            $favor->entregador_id = $entregador_novo->id;
            $favor->save();

            $entregador_novo->push_reload();
            if (isset($entregador_antigo)) {
                $entregador_antigo->push_reload();
            }
            DB::commit();

            return [];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    private function deliverymanQualified($pedido, $entregador)
    {
        $deliverymanMetrics = DB::select("SELECT
                                    e.id entregador_id,
                                    ifnull((select count(id) from entregas where estado = 'C' and entregas.entregador_id = e.id group by entregador_id), 0) +
                                    ifnull((select count(id) from favores where estado = 'CONCLUIDO' and favores.entregador_id = e.id group by entregador_id), 0) finished_deliveries,
                                    ifnull((select sum(valor) from pagamentos where pagamentos.estado = 'P' and pagamentos.entregador_id = e.id group by entregador_id), 0) +
                                    ifnull((
                                        select sum( case when r.tipo = 'RETENCAO' then -pr.valor else pr.valor end) from parcelas_retencao pr
                                        inner join retencoes r on r.id = pr.retencao_id
                                        where r.estado = 'ANDAMENTO' and pr.estado = 'PENDENTE' and r.entregador_id = e.id
                                    ), 0)/100
                                    balance
                                from entregadores e 
                                    where e.estado = 'A' 
                                        and not e.banido
                                        and e.id = {$entregador->id}");

        if (!isset($deliverymanMetrics[0])) {
            return ["available" => true, "message" => "", "balance" => 0];
        }
        $entregador->metrics = $deliverymanMetrics[0];
        $result = BlockedFromTakingCashOrder::passes($pedido, $entregador);

        $balance = (($entregador->metrics->balance * 100) - $pedido->valor_declarado) / 100;
        $message = "Entregador selecionado terá um saldo de R$ {$balance} após finalizar esta entrega. Verifique se o {$entregador->user->nome} {$entregador->user->sobrenome} é o melhor entregador disponível no momento.";

        $available = ["available" => $result, "message" => $message, "balance" => $balance];

        return $available;
    }

    /**
     * Log de mudança de entregador no pedido
     *
     * @param [type] $entregador_antigo
     * @param [type] $entregador_novo
     * @param [type] $pedido
     * @return void
     */
    public function logDeliverymanChange($entregador_antigo, $entregador_novo, $pedido)
    {
        $context = [
            '[::operador]' => Auth::user()->nome . ' ' . Auth::user()->sobrenome,
            '[::shipper1]' => isset($entregador_antigo) ? "{$entregador_antigo->user->nome_completo}" : "Nenhum entregador anterior",
            '[::shipper2]' => "{$entregador_novo->user->nome_completo}"
        ];

        $pedido->add_atividade('ADMIN_CHANGE_DELIVERYMAN_ORDER', $context);
    }
}