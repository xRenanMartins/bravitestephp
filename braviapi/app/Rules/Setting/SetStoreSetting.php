<?php

namespace App\Rules\Setting;

use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Setting;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;

class SetStoreSetting
{
    public static function execute(Store $store, string $settingLabel, $value, $active = true)
    {
        try {
            $settings = self::getSettings();
            $setting = $settings->where('label', $settingLabel)->first();
            if(isset($setting)){
                $store->dbSettings()->syncWithoutDetaching([$setting->id => [
                    'value' => $setting->setOf($value),
                    'is_active' => $active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);
            }

        } catch (\Exception $e){
            app('sentry')->captureException($e);
        }
    }

    public static function clearCache()
    {
        Cache::forget('store_active_settings');
    }

    public static function getSettings()
    {
        return Cache::remember('store_active_settings', 86400, function () {
            return Setting::withoutGlobalScope(DomainScope::class)
                ->where("is_active", true)
                ->where("tag", "STORE")
                ->select(['id', 'name', 'label', 'domain_id', 'type'])->get();
        });
    }
}