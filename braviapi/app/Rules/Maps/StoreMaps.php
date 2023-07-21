<?php

namespace App\Rules\Maps;

use Illuminate\Support\Facades\DB;

class StoreMaps
{
    public static function execute($domainId = null)
    {
        if (!is_null($domainId) && $domainId != '') {
            return DB::table("nearest_stores")
                ->select("id", "name as nome")
                ->get();
        }

        return DB::table("nearest_stores")
            ->select("id", "name as nome", "is_open as aberto", "domain_id")
            ->selectRaw("1 as habilitado")
            ->selectRaw("ST_AsText(location) as location")
            ->selectRaw("IF(type like '%PARCEIRO%', true, false) as parceiro")
            ->groupBy("id")
            ->get()
            ->map(function ($store) {
                $position = explode(" ", str_replace("POINT(", "", str_replace(")", "", $store->location)));
                info($position);
                $store->latitude = isset($position[0]) ? floatval($position[0]) : null;
                $store->longitude = isset($position[1]) ? floatval($position[1]) : null;
                unset($store->location);
                return $store;
            });
    }
}
