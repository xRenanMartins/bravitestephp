<?php

namespace App\Rules\UserAudity;

use Packk\Core\Models\Order;
use Packk\Core\Integration\Payment\Transaction;

class AntifraudLog
{
    protected $cardVerified;

    public function __construct()
    {
        $this->cardVerified = false;
    }

    public function execute(Order $order)
    {
        $cardAudit = $order->cliente->user->audits->where("type", "RANDOM_TRANSACTION")
            ->where("parent_reference_id", $order->id)
            ->first();
        $cardsVerified = $order->cliente->user->audits->where("type", "RANDOM_TRANSACTION")
            ->where("action", "VERIFIED");

        $legitiResponse = $order->antifrauds()->where('type', 'ANALIZE_FRAUD_LEGITI')->first();
        $legitiResponseData = !empty($legitiResponse) ? $legitiResponse->getValue() : [];
        $antfraudVal = ($cardAudit != null) ? number_format($cardAudit->value / 100, 2, ",", ".") : number_format(rand(100, 400) / 100, 2, ",", ".");

        if ($cardsVerified->count() > 0) {
            try {
                foreach ($cardsVerified as $card) {
                    $cardAnalyzed = $this->getVerifyCardUser($card->parent_reference_id ?? $order->id, $card->analyzed_user_id);
                    $cardOrder = $this->getVerifyCardUser($order->id, $order->cliente->user_id);

                    if (!is_null($cardAnalyzed) && !is_null($cardOrder) && $cardAnalyzed == $cardOrder) {
                        if (!$this->cardVerified) $this->cardVerified = true;
                    }

                    if ($this->cardVerified) {
                        $antfraudVal = ($card->value != null) ? number_format($card->value / 100, 2, ",", ".") : number_format(rand(100, 400) / 100, 2, ",", ".");
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return [
            'pedido' => $order,
            'checkboxes' => $this->checkboxes(),
            'antfraudVal' => $antfraudVal,
            'cardAudit' => $cardAudit,
            'cardVerified' => $this->cardVerified,
            'legitiResponse' => $legitiResponseData
        ];
    }

    private function checkboxes()
    {
        return [
            "omie" => [
                "title" => "Comparação de dados Omie",
                "data" => [[
                    "name" => "omie-address",
                    "description" => "Endereço"
                ],
                    [
                        "name" => "omie-phone",
                        "description" => "Telefone"
                    ],
                    [
                        "name" => "omie-email",
                        "description" => "Email"
                    ]],
            ],
            "info-customer" => [
                "title" => "Ligação para confirmar dados com o cliente",
                "data" => []
            ],
            "chat" => [
                "title" => "Análise feita pelo chat",
                "data" => [[
                    "name" => "chat-document",
                    "description" => "Transação aleatoria na fatura"
                ],
                    [
                        "name" => "chat-photo",
                        "description" => "Foto segurando o documento"
                    ]]
            ]
        ];
    }

    private function getVerifyCardUser($orderId, $userId)
    {
        $order = Order::find($orderId);
        if ($order->info->bin_card) {
            return substr($order->info->bin_card, 0, 4) . "-" . $userId;
        } else {
            $transaction = (new Transaction($order->zoop_transaction_id))->showRaw();
            return $transaction->first_digits . "-" . $userId;
        }

        return null;
    }
}