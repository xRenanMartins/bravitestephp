<?php

namespace App\Rules\Group;

use Packk\Core\Models\GroupAssociation;
use Packk\Core\Models\Group;

class ShowGroup
{
    public function execute($id)
    {
        $group = Group::with(['settings'])->findOrFail($id);

        $ids = GroupAssociation::where('group_id', $group->id)->where('fixed', true)
            ->select('model_id')->get()->pluck('model_id');
        $groupData = $group->only([
            'id',
            'name',
            'description',
            'type',
            'status',
            'domain_id',
            'created_at',
        ]);

        $settingsData = $group->settings->map->only([
            'type',
            'comparator',
            'date_min',
            'date_max',
            'quantity_min',
            'quantity_max'
        ]);

        return array_merge($groupData, [
            'ids' => $ids,
            'settings' => $settingsData,
            'categories' => $group->categories->pluck('id')
        ]);
    }
}