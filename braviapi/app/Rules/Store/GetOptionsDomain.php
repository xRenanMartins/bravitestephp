<?php

namespace App\Rules\Store;

use App\Rules\Store\V2\GetSettingsStore;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Models\PaymentMethod;
use Packk\Core\Models\Setting;
use Packk\Core\Models\Store;

class GetOptionsDomain
{
    public function execute($payload)
    {
        $domain = currentDomain(true);

        if (json_decode($payload["cloneStore"])) {
            return response()->json([
                'stores' => Store::identic('domain_id', $domain->id)
                    ->whereRaw('is_partner(type) = 0')
                    ->get(),
            ]);
        }

        $type_store_options = $domain->getSetting("type_store_options", json_decode(json_encode([
            ["value" => "PARCEIRO_NORMAL", "text" => "Parceiro"],
            ["value" => "PARCEIRO_EXCLUSIVO", "text" => "Parceiro Exclusivo"],
            ["value" => "PARCEIRO_MARKETPLACE_NORMAL", "text" => "Marketplace"],
            ["value" => "PARCEIRO_MARKETPLACE_EXCLUSIVO", "text" => "Marketplace Exclusivo"],
            ["value" => "PARCEIRO_LOCAL_NORMAL", "text" => "Local"],
            ["value" => "PARCEIRO_LOCAL_EXCLUSIVO", "text" => "Local Exclusivo"],
        ])));
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->groupBy('label')
            ->orderBy('type')
            ->orderBy('label', 'asc')
            ->get();

        $hasSignature = $domain->hasFeature('signature');

        $payment_methods_groups = array();
        foreach ($paymentMethods as $method) {
            $payment_methods_groups[$method->mode][$method->type][] = $method;
        }

        $store = false;
        $store_type_check = "LOCAL";
        $formInputs = InputsPaymentMethod::execute($payment_methods_groups, $store, $store_type_check);

        $settingsDomain = Setting::select('label')
            ->where(function ($q) use ($domain) {
                $q->whereNull('domain_id')->orWhere('domain_id', $domain->id);
            })->where('tag', 'STORE')->where('is_active', 1)
            ->get()->pluck('label')->toArray();

        return response()->json([
            'hasSignature' => $hasSignature,
            'type_store_options' => $type_store_options,
            'paymentMethods' => $paymentMethods,
            'payment_methods_groups' => $payment_methods_groups,
            "settings" => (new GetSettingsStore())->execute(),
            'formInputs' => $formInputs,
            'settingsDomain' => $settingsDomain,
            'active_deliveryman_automatic_bonus' => $domain->hasFeature("deliveryman_automatic_bonus"),
            'dynamic_mdr' => $domain->getSetting("mdr_percentage"),
            'is_master' => Auth::user()->hasRole('master|manager-strategy')
        ]);
    }
}
