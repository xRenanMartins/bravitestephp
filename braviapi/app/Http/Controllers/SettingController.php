<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-05-10
 * Time: 23:12
 */

namespace App\Http\Controllers;

use App\Jobs\SyncCategoryFreeShipp;
use App\Rules\Setting\SearchTarget;
use App\Rules\Setting\SetStoreSetting;
use App\Rules\Setting\SettingUsing;
use App\Rules\Setting\ShowMenu;
use App\Rules\Store\V2\UpdateSettingsStore;
use Illuminate\Http\Request;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\Store;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Product;
use Packk\Core\Models\Setting;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Shift;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Models\User;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        return Setting::withoutGlobalScope(DomainScope::class)
            ->like('label', $request->search)
            ->identic('tag', $request->tag)
            ->orderBy('tag', 'ASC')
            ->orderBy('label', 'ASC')
            ->simplePaginate($request->length);
    }

    public function store(Request $request)
    {
        $payload = $this->validate($request, Setting::storeRules());
        $setting = Setting::create($payload);
        if ($payload['tag'] === 'STORE') {
            SetStoreSetting::clearCache();
        }
        return $setting;

    }

    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, Setting::updateRules());
        $setting = Setting::findOrFail($id);
        if ($setting->tag === 'STORE') {
            SetStoreSetting::clearCache();
        }
        $setting->update($payload);
        return ["status"=>$setting];
    }

    public function destroy($id)
    {
        try {
            $setting = Setting::findOrFail($id);
            if ($setting->tag === 'STORE') {
                SetStoreSetting::clearCache();
            }
            $setting->delete();
            return response(true);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function setting_using($feature_id, $tag, Request $request, SettingUsing $settingUsing)
    {
        $payload = $this->validate($request, [
            'search' => 'sometimes'
        ]);
        $lines = $settingUsing->execute($payload, $feature_id, $tag);

        return response()->json($lines, 200);
    }

    public function get_setting_target($setting_id, $tag, $target_id)
    {
        switch ($tag) {
            case 'customer':
                $instance = Customer::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;
            case 'domain':
                $instance = Domain::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'product':
                $instance = Product::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'shift':
                $instance = Shift::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'showcase':
                $instance = Showcase::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'store':
                $instance = Store::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'zone':
                $instance = AreaServed::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;
            case 'user':
                $instance = User::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;
        }
        if ($instance) {
            Cache::forget("{$tag}.{$instance->id}.settings");
            $setting = Setting::withoutGlobalScope(DomainScope::class)->find($setting_id);

            if ($setting) {
                return response()->json([
                    "data" => $instance->getSetting($setting->label)
                ], 200);
            } else {
                return response()->json([
                    "message" => "Funcionalidade não existe",
                ], 500);
            }
        } else {
            return response()->json([
                "message" => $tag . " não existe"
            ], 500);
        }
    }

    public function set_setting_target(Request $request, $setting_id, $tag, $target_id)
    {
        $payload = $this->validate($request, [
            'value' => 'required',
        ]);

        switch ($tag) {
            case 'customer':
                $instance = Customer::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;
            case 'domain':
                $instance = Domain::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'product':
                $instance = Product::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'shift':
                $instance = Shift::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'showcase':
                $instance = Showcase::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'store':
                $instance = Store::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'zone':
                $instance = AreaServed::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'user':
                $instance = User::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

        }

        if ($instance) {
            $setting = Setting::withoutGlobalScope(DomainScope::class)->find($setting_id);
            if ($setting) {
                Cache::forget("{$tag}.{$setting->label}.settings");
                Cache::forget("{$tag}.{$instance->id}.settings");
                if ($request->type === 'JSON') {
                    $payload['value'] = json_decode($payload['value']);
                }

                try {
                    $jsonLogPrevious = json_encode([
                        'setting_id' => $setting_id,
                        'instance_id' => $instance->id,
                        'old_value' => $instance->getSetting($setting->label)
                    ]);
                    $jsonLog = json_encode([
                        'setting_id' => $setting_id,
                        'instance_id' => $instance->id,
                        'new_value' => $payload['value']
                    ]);
                    LogTable::log('UPDATE', "setting:{$setting->label}", $instance->id, 'many', $jsonLogPrevious, $jsonLog);
                } catch (\Exception) {}

                if ($tag == 'store' && $setting->label == 'receive_after') {
                    UpdateSettingsStore::saveActivityAboutReceiveAfter($instance, $payload['value'], true);
                }

                $instance->setSetting($setting->label, $payload['value']);

                if ($tag == 'store') {
                    if (in_array($setting->label, ['age_range', 'cart_limit', 'discount_delivery'])) {
                        dispatch(new SendShopFeedEvent($instance->id, 'settings:update', [$setting->label]));
                    }

                    if ($setting->label == 'signature') {
                        dispatch(new SendShopFeedEvent($instance->id, 'store:setting.subscription'));
                    }

                    if ($setting->label == 'discount_delivery') {
                        dispatch(new SyncCategoryFreeShipp($instance));
                    }
                }

                return response()->json([
                    "message" => "Funcionalidade [" . $setting_id . "] atribuida para o " . $tag . " [" . $target_id . "]",
                ], 200);
            } else {
                return response()->json([
                    "message" => "Funcionalidade não existe",
                ], 500);
            }
        } else {
            return response()->json([
                "message" => $tag . " não existe"
            ], 500);
        }
    }

    public function update_setting_target(Request $request, $setting_id, $tag, $target_id)
    {
        $payload = $this->validate($request, [
            'is_active' => 'required'
        ]);

        switch ($tag) {
            case 'customer':
                $instance = Customer::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'domain':
                $instance = Domain::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'product':
                $instance = Product::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'shift':
                $instance = Shift::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'showcase':
                $instance = Showcase::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'store':
                $instance = Store::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'zone':
                $instance = AreaServed::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;

            case 'user':
                $instance = User::withoutGlobalScope(DomainScope::class)->where("id", $target_id)->first();
                break;
        }

        if ($instance) {
            Cache::forget("{$tag}.{$instance->id}.settings");
            $setting = Setting::withoutGlobalScope(DomainScope::class)->find($setting_id);

            $return = $instance->dbSettings()->syncWithoutDetaching([$setting_id => ['is_active' => $payload['is_active']]]);

            if ($tag == 'store' && $setting->label == 'receive_after') {
                Cache::forget("{$tag}.{$instance->id}.settings");
                UpdateSettingsStore::saveActivityAboutReceiveAfter($instance, $instance->getSetting('receive_after'), $payload['is_active']);
            }

            if ($tag == 'store') {
                if (in_array($setting->label, ['age_range', 'cart_limit', 'discount_delivery', 'minimun_order', 'max_value_order'])) {
                    dispatch(new SendShopFeedEvent($instance->id, 'settings:update', [$setting->label]));
                }

                if ($setting->label == 'signature') {
                    dispatch(new SendShopFeedEvent($instance->id, 'store:setting.subscription'));
                }
            }

            return response()->json([
                "message" => $return,
            ], 200);
        } else {
            return response()->json([
                "message" => $tag . " não existe"
            ], 500);
        }
    }

    public function search_setting_target(Request $request, $setting_id, $tag, SearchTarget $searchTarget)
    {
        $payload = $this->validate($request, [
            'q' => 'required'
        ]);

        $lines = $searchTarget->execute($payload, $setting_id, $tag);

        return response()->json($lines, 200);
    }

    public function menu(ShowMenu $showMenu)
    {
        return $showMenu->execute();
    }
}