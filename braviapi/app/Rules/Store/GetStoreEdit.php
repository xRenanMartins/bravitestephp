<?php

namespace App\Rules\Store;

use App\Rules\Store\V2\GetSettingsStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Category;
use Packk\Core\Models\PaymentMethod;
use Packk\Core\Models\Setting;
use Packk\Core\Models\Store;
use Packk\Core\Models\User;

class GetStoreEdit
{
    private $store;

    public function execute($id)
    {
        $this->store = Store::with('domain')->find($id);
        $paymentMethodsStore = $this->store->payment_methods()
            ->groupBy('label')
            ->orderByDesc('type')
            ->orderBy('label')
            ->get();
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->groupBy('label')
            ->whereNotIn('id', $paymentMethodsStore->pluck('id'))
            ->orderBy('type')
            ->orderBy('label')
            ->get();

        $paymentMethods = $paymentMethods->merge($paymentMethodsStore);
        $domain = $this->store->domain;

        $payment_methods_groups = array();
        foreach ($paymentMethods as $method) {
            $payment_methods_groups[$method->mode][$method->type][] = $method;
        }

        $type_store_options = $domain->getSetting("type_store_options", [
            ["value" => "PARCEIRO_NORMAL", "text" => "Parceiro"],
            ["value" => "PARCEIRO_EXCLUSIVO", "text" => "Parceiro Exclusivo"],
            ["value" => "PARCEIRO_MARKETPLACE_NORMAL", "text" => "Marketplace"],
            ["value" => "PARCEIRO_MARKETPLACE_EXCLUSIVO", "text" => "Marketplace Exclusivo"],
            ["value" => "PARCEIRO_LOCAL_NORMAL", "text" => "Local"],
            ["value" => "PARCEIRO_LOCAL_EXCLUSIVO", "text" => "Local Exclusivo"]
        ]);

        $store_type_check = "LOCAL";
        $formInputs = InputsPaymentMethod::execute($payment_methods_groups, $this->store, $store_type_check);

        $shopkeeper = $this->store->shopkeeper;
        $shopkeeperUser = User::find($shopkeeper->user_id);
        $shopkeeper->setAttribute('user', $shopkeeperUser);
        if (!empty($shopkeeper->premium_at)) {
            $shopkeeper->setAttribute('premium_at', Carbon::parse($shopkeeper->premium_at)->format('d/m/Y H:i'));
        }

        $settingsDomain = Setting::select('label')
            ->where(function ($q) use ($domain) {
                $q->whereNull('domain_id')->orWhere('domain_id', $domain->id);
            })->where('tag', 'STORE')->where('is_active', 1)
            ->get()->pluck('label')->toArray();

        $categoriesStore = Category::where('is_primary', 1)->where('ativo', 1)->where('tipo', 'L')->get();

        $categoriesSelected = Category::query()
            ->join('categoria_loja', 'categoria_loja.categoria_id', '=', 'categorias.id')
            ->where('categoria_loja.loja_id', $this->store->id)
            ->where('is_primary', 1)->where('ativo', 1)
            ->groupBy('categorias.id')->select('categorias.id')->get()->pluck('id');

        $user = Auth::user();
        $inRegister = in_array($this->store->status, ['PRE_ACTIVATED', 'PRE_ACTIVATED_ANALYZE', 'PRE_ACTIVATED_ADMIN']);
        return response()->json([
            "store" => $this->store,
            "others" => $this->getSettingsOld(),
            "settings" => [], // TODO
            "paymentMethods" => $paymentMethods,
            "categoriesStore" => $categoriesStore,
            "shopkeeper" => $shopkeeper,
            "categorySelected" => $categoriesSelected,
            "storeAddress" => $this->store->addresses->first(),
            "deliveryMode" => $this->store->getSetting("loggi_delivery", '0'),
            "is_scheduling" => isset($this->store->schedule) && $this->store->schedule->is_scheduling,
            "type_store_options" => $type_store_options,
            "domain" => $domain,
            "hasSignature" => $domain->hasFeature('signature'),
            'payment_methods_groups' => $payment_methods_groups,
            'formInputs' => $formInputs,
            'settingsDomain' => $settingsDomain,
            'active_deliveryman_automatic_bonus' => $domain->hasFeature("deliveryman_automatic_bonus"),
            'is_master' => $user->hasRole('master|manager-strategy'),
            'can_modify_commission' => $inRegister || $user->isFranchiseOperator() || $user->hasRole($domain->getSetting('can_modify_commission', 'master')),
            'deeplink' => $this->store->link()
        ]);
    }

    private function getSettingsOld()
    {
        return [
            'receive_after' => $this->store->getSetting('receive_after'),
            'offline_commission' => $this->store->getSetting('offline_commission'),
            'is_test' => $this->store->getSetting("is_test"),
            'dynamic_mdr' => $this->store->domain->getSetting("mdr_percentage"),
        ];
    }

    private function formatFloat($value)
    {
        return $value / 100;
    }
}
