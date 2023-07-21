<?php

namespace App\Rules\Sales;

use Packk\Core\Models\Order;
use Packk\Core\Integration\Payment\Transaction;

class ReverseTransaction
{
    public function execute($payload)
    {
        try {
            $order = Order::with('customer.user.audits')->find($payload->order_id);

            $audit = $order->customer->user->audits->where('value', $payload->value)
                ->where('action', 'CREATED')->where("type", "RANDOM_TRANSACTION")->first();

            if ($audit) {
                $transaction = new Transaction(($audit->service_id ?? $audit->reference_id), true);
                return $transaction->voidfull();
            } else {
                return null;
            }
        } catch (\Exception $e) {
            app('sentry')->captureException($e);
            throw $e;
        }
    }
}