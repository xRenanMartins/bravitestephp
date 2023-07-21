<?php

namespace App\Rules\Group;

use App\Jobs\Groups\SyncFixedIdsInGroup;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\Group;
use Packk\Core\Models\GroupSettings;
use Packk\Core\Models\Store;

class NewGroup
{
    public function execute($payload)
    {
        $group = Group::create([
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'type' => $payload['type'],
            'status' => 'ACTIVE',
            'domain_id' => currentDomain()
        ]);

        if (!empty($payload['categories'])) {
            $group->categories()->attach($payload['categories'], ['fixed' => 1]);
        }

        if (isset($payload['group_settings'])) {
            foreach ($payload['group_settings'] as $groupSetting) {
                $payloadGroupSettings = !is_array($groupSetting) ? (array)json_decode($groupSetting) : $groupSetting;
                $payloadGroupSettings["group_id"] = $group->id;

                GroupSettings::create($payloadGroupSettings);
            }
        }

        if (isset($payload['ids']) && !empty($payload['ids']) && count($payload['ids']) > 0) {
            switch ($payload['type']) {
                case 'L':
                    $ids = Store::select('id')->whereIn('id', $payload['ids'])->get();
                    if ($ids->isEmpty()) {
                        throw new CustomException('Os ids enviados são inválidos');
                    }
                    dispatch(new SyncFixedIdsInGroup($group, 'stores', $ids->pluck('id')->toArray()));
                    break;
                case 'C':
                    $ids = Customer::select('id')->whereIn('id', $payload['ids'])->get();
                    if ($ids->isEmpty()) {
                        throw new CustomException('Os ids enviados são inválidos');
                    }
                    dispatch(new SyncFixedIdsInGroup($group, 'clients', $ids->pluck('id')->toArray()));
                    break;
                case 'E':
                    $ids = Deliveryman::select('id')->whereIn('id', $payload['ids'])->get();
                    if ($ids->isEmpty()) {
                        throw new CustomException('Os ids enviados são inválidos');
                    }
                    dispatch(new SyncFixedIdsInGroup($group, 'deliveries', $ids->pluck('id')->toArray()));
                    break;
            }
        }

        return $group;
    }
}