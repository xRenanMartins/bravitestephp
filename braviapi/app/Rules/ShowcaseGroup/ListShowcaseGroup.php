<?php

namespace App\Rules\ShowcaseGroup;

use Packk\Core\Models\ShowcaseGroup;

class ListShowcaseGroup
{
    public function execute($request)
    {
        $perPage = $request->get('length', 10);

        $query = ShowcaseGroup::query()
            ->identic('id', $request->id)
            ->identic('active', $request->active);

        if ((int)$request->get('page', 1) === 1) {
            $groupIds = $query->select('showcase_groups.id')->get()->pluck('id')->toArray();
            $total = count($groupIds);
        }

        $data = $query
            ->select([
                'showcase_groups.id',
                'showcase_groups.title',
                'showcase_groups.image',
                'showcase_groups.ordem',
                'showcase_groups.active',
                'showcase_groups.domain_id'
            ])
            ->orderByDesc('showcase_groups.id')
            ->simplePaginate($perPage);

        $response = $data->toArray();
        if (isset($total)) {
            $response['total'] = $total;
        }
        return $response;
    }
}