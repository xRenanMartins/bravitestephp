<?php

namespace App\Rules\Store\V2;

use Illuminate\Support\Str;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\PaymentMethod;
use Packk\Core\Models\Store;

class UpdateStorePaymentMethods
{
    private Store $store;
    private array $payload;

    public function execute($storeId, array $payload)
    {
        $this->payload = $payload;
        $this->store = Store::with(['all_payment_methods'])->findOrFail($storeId);

        foreach ($this->payload as $key => $value) {
            $payment = PaymentMethod::where("label", Str::upper($key))->first();

            if (!empty($payment)) {
                $this->handlePayment($payment, $value);

                if ($value && $payment->provider == 'SOFTWARE-EXPRESS') {
                    $this->handleSoftwareExpress($payment, $key);
                }
            }
        }

        dispatch(new SendShopFeedEvent($this->store->id, 'rules:change', ['payment_methods']))->afterCommit();
    }

    private function handlePayment(PaymentMethod $payment, $checked)
    {
        if ($payment->label == "DINHEIRO") {
            $this->store->dinheiro_ativo = $checked;
            $this->store->save();
        }

        $paymentSelected = $this->store->all_payment_methods()->where('payment_methods.id', $payment->id)->first();
        if (isset($paymentSelected->pivot)) {
            if ($payment->mode === 'ONLINE') {
                $paymentSelected->pivot->is_active = $checked;
                $paymentSelected->pivot->deleted_at = null;
            } else {
                $paymentSelected->pivot->deleted_at = !$checked ? now() : null;
            }
            $paymentSelected->pivot->save();
        } else {
            $values = $payment->mode === 'ONLINE'
                ? ['is_active' => $checked, 'deleted_at' => null]
                : ['deleted_at' => !$checked ? now() : null];
            $this->store->all_payment_methods()->syncWithoutDetaching([$payment->id => $values]);
        }
    }

    private function handleSoftwareExpress(PaymentMethod $payment, $label)
    {
        $paymentSelected = $this->store->all_payment_methods()->where('payment_methods.id', $payment->id)->first();

        if (isset($this->payload["{$label}_merchant_id"])) {
            $paymentSelected->pivot->merchant_id = $this->payload["{$label}_merchant_id"];
        }
        if (isset($this->payload["{$label}_merchant_key"])) {
            $paymentSelected->pivot->merchant_key = $this->payload["{$label}_merchant_key"];
        }
        if (isset($this->payload["{$label}_settings"])) {
            $paymentSelected->pivot->settings = ["commission" => $this->payload["{$label}_settings"]];
        }
        $paymentSelected->pivot->save();
    }
}