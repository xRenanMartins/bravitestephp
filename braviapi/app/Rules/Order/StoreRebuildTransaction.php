<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-29
 * Time: 21:10
 */

namespace App\Rules\Order;

use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\Order;
use Packk\Core\Models\Activity;
use Packk\Core\Integration\Payment\ProcessPayment;
use Packk\Core\Integration\Payment\Transaction;
use Packk\Core\Actions\Customer\Financial\Calculate;
use Packk\Core\Traits\Concierge;
use Packk\Core\Util\Formatter;
use Packk\Core\Integration\Payment\Buyer;
use Carbon\Carbon;

class StoreRebuildTransaction
{
    use Concierge;

    private $seller;

    public function execute($payload, $user)
    {

        $this->seller = env('ZOOP_SHIPP_SELLER_ID');
        try {
            $order = Order::findOrFail($payload->pedido_id);
            if (isset($order->zoop_transaction_id)
                && ($order->metodo_pagamento == "CARTAO_CREDITO")
                && ($order->estado == 'F' || $order->estado == "T" || $order->estado == 'A')) {
                $original_transaction = new Transaction($order->zoop_transaction_id, true);
                if (isset($original_transaction->id)) {
                    $original_amount = Formatter::money($original_transaction->amount);
                    if (ProcessPayment::validaStatus($original_transaction)) {
                        $original_transaction->voidfull();
                    }
                    if ($order->tipo == "CONCIERGE" && isset($payload->valor) && isset($payload->comissao)) {
                        $novo_valor = intval(str_replace(".", "", str_replace(",", "", str_replace("R$", "", $payload->valor))));
                        if ($novo_valor > 0) {
                            $teto = $this->maximumServiceDeliveryFee() - min($order->taxa_entrega_cliente, $this->maximumServiceDeliveryFee());
                            $order->valor = $novo_valor;
                            $order->comissao_concierge = intval(min($order->valor * ($payload->comissao / 100), $teto));
                        }
                    }
                    $amount = Calculate::amountOrder($order);
                    $timestamp = Carbon::now()->timestamp;
                    $order->save();
                    if ($amount > 0) {
                        $zoop_buyer = new Buyer($order->buyer_id);
                        $transaction = $zoop_buyer->charge(
                            [
                                "amount" => $amount,
                                "description" => "Pagamento Pedido {$order->id}",
                                "on_behalf_of" => defaultSeller(),
                                "capture" => false,
                                "source_id" => $order->id,
                                "source_provider" => "pedido-shipp",
                                "type" => "R-{$timestamp}"
                            ]
                        );
                        $amount_format = Formatter::money($amount);
                        $name = Formatter::nameUser($user);
                        if (ProcessPayment::validaStatus($transaction)) {
                            $order->zoop_transaction_id = $transaction->id;
                            $order->save();
                            $order->add_atividade(Activity::PEDIDO_NORMAL_ALTERADO_SUCESSO_OPERADOR,
                                ['[::valor_original]' => $original_amount, '[::valor]' => $amount_format, '[::usuario]' => $name]);
                            return ['message' => "Sucesso"];
                        } else {
                            $order->add_atividade(Activity::PEDIDO_NORMAL_ALTERADO_ERROR_OPERADOR,
                                ['[::valor_original]' => $original_amount, '[::valor]' => $amount_format, '[::usuario]' => $name]);
                            throw new RuleException('Ops, ocorreu erro...', "Erro ao recuperar dados da transação.");
                        }
                    }
                }
            }
            return ['message' => "Sucesso"];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}