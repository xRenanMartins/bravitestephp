<?php

namespace App\Rules\Store\V2;

use App\Rules\Setting\SetStoreSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Store;
use Packk\Core\Models\StoreActivity;

class UpdateSettingsStore
{
    public function execute($payload, $storeId = null)
    {
        Cache::forget("store.{$storeId}.settings");

        $store = null;
        if (!empty($storeId)) {
            $store = Store::with(['shopkeeper'])->findOrFail($storeId);
        }

        foreach ($payload as $item) {
            $item = (object)$item;
            if ($item->show && !$item->disabled) {
                if ($item->is_setting) {
                    $value = ($item->type ?? '') === 'money' ? $item->value * 100 : $item->value;
                    SetStoreSetting::execute($store, $item->setting, $value);
                } else if ($item->shopkeeper ?? false) {
                    $store->shopkeeper[$item->setting] = $item->value;
                } else if (!($item->only_check ?? false) || ($item->is_store ?? false)) {
                    $isOrdem = $item->setting == 'ordem' && empty($item->value) && $item->value !== 0;
                    $store[$item->setting] = $isOrdem ? 9999 : $item->value;
                }
                foreach ($item->inputs as $input) {
                    $input = (object)$input;
                    $inputValue = $input->type === 'money' ? $input->value * 100 : $input->value;

                    if ($input->shopkeeper ?? false) {
                        if ($input->type == 'datetime') {
                            $inputValueData = !empty($input->value) ? Carbon::createFromFormat('d/m/Y H:i', $input->value) : null;
                            $store->shopkeeper[$input->setting] = $item->value ? $inputValueData : null;
                        } else {
                            $store->shopkeeper[$input->setting] = $inputValue;
                        }
                    } else {
                        if ($input->setting == 'receive_after') {
                            self::saveActivityAboutReceiveAfter($store, $inputValue, $item->value);
                        }
                        SetStoreSetting::execute($store, $input->setting, $inputValue, $item->value);
                    }
                }
            }
        }

        $store->shopkeeper->save();
        $store->save();
    }

    public static function saveActivityAboutReceiveAfter($store, $newValue, $active): void
    {
        $oldValue = $store->getSetting('receive_after');

        $storeActivity = new StoreActivity();
        $storeActivity->user_id = Auth::id();
        $storeActivity->store_id = $store->id;

        if (!$active) {
            $storeActivity->description = "O plano de recebimento personalizado foi desativado";
        } else if ($oldValue == $newValue) {
            $storeActivity->description = "Plano de recebimento de {$oldValue} dias ativado";
        } else {
            $storeActivity->description = !empty($oldValue)
                ? "Plano de recebimento alterado de {$oldValue} para {$newValue} dias"
                : "Plano de recebimento alterado para {$newValue} dias";
        }

        $storeActivity->activity = 'ALTERAR_PLANO_DE_RECEBIMENTO';
        $storeActivity->save();
    }
}
