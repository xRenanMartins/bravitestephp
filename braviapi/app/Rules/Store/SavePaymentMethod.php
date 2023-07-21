<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-08-03
 * Time: 17:47
 */

namespace App\Rules\Store;

use Packk\Core\Models\Store;
use Packk\Core\Models\PaymentMethod;
use Packk\Core\Integration\Payment\Seller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SavePaymentMethod
{
    public static function execute($payload, $store, $verifySeller = true, $verifyTypeStore = false)
    {
        foreach ($payload as $key => $formPayment) {
            $payments = PaymentMethod::where("label", $key)->get();

            foreach ($payments as $payment) {
                if ($payment) {
                    $active = isset($formPayment->{$key}) && $formPayment->{$key};

                    if (isset($formPayment->merchant_id) && isset($formPayment->merchant_key)) {
                        $payload = "?service_id={$formPayment->merchant_id}&service_provider=SOFTWARE-EXPRESS";
                        $payload .= "&reference_id={$store->id}&reference_provider=loja-shipp";
                        $payload .= "&service_key={$formPayment->merchant_key}";

                        if ($verifySeller) {
                            $seller = (new Seller())->searchSeller($payload);
                            if (!$seller) {
                                $seller = Seller::createSeller($store, $formPayment->merchant_id, $formPayment->merchant_key);
                            }
                        }
                        $store->all_payment_methods()->syncWithoutDetaching([$payment->id => [
                            'is_active' => $store->is_marketplace ? false : $active,
                            'merchant_id' => $formPayment->merchant_id,
                            'merchant_key' => $formPayment->merchant_key,
                            "settings" => $formPayment->settings ? ["commission" => $formPayment->settings] : null,
                            "seller_id" => (isset($seller) && isset($seller->id)) ? $seller->id : ($payment->pivot->seller_id ?? null)
                        ]]);
                    } else {
                        if ($payment->provider != 'SOFTWARE-EXPRESS') {
                            if ($key == "DINHEIRO") {
                                $store->dinheiro_ativo = $formPayment->{$key} ? true : false;
                            }

                            if ($key == "cartao_credito" && !is_null($store->zoop_seller_id)) {
                                $new_values = ['deleted_at' => null, 'is_active' => !$store->is_marketplace];
                                if ($store->domain_id == 1) {
                                    if ($verifyTypeStore != "PARCEIRO_NORMAL" && $verifyTypeStore != "PARCEIRO_EXCLUSIVO") {
                                        $new_values = ['deleted_at' => null, 'is_active' => $active];
                                    }
                                }
                            } else if ($formPayment->{$key} && $payment->mode == "ONLINE") {
                                $new_values = ['deleted_at' => null, 'is_active' => $active];
                            } else if ($formPayment->{$key} && $payment->mode == "OFFLINE") {
                                $new_values = ['deleted_at' => null];
                            } else {
                                $new_values = ['deleted_at' => $payment->mode == "OFFLINE" ? Carbon::now() : null, 'is_active' => false];
                            }
                            // [keeps the last merchant payment checked only if the method is on ]
                            DB::transaction(function () use ($store, $payment, $new_values) {
                                $store->all_payment_methods()->syncWithoutDetaching([$payment->id => $new_values]);
                            }, 3);
                        } else {
                            if (!$active) {
                                $store->all_payment_methods()->syncWithoutDetaching([$payment->id => [
                                    'is_active' => false,
                                    'deleted_at' => $payment->mode == "OFFLINE" ? Carbon::now() : null
                                ]]);
                            }
                        }
                    }
                }
            }
        }
    }
}