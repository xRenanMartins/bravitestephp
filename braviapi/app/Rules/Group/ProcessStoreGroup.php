<?php

namespace App\Rules\Group;

use Packk\Core\Models\Group;
use Packk\Core\Models\GroupSettings;
use Packk\Core\Models\Store;

class ProcessStoreGroup
{
    public function execute(Group $group)
    {
        $settings = $group->settings;
        $ids = null;

        $categories = $group->categories()->pluck('categorias.id');
        if (count($categories) > 0) {
            $ids = self::categoriesFilter($categories);
        }

        if (is_null($ids) || count($ids) > 0) {
            foreach ($settings as $setting) {
                switch ($setting->type) {
                    case 'REGISTER_DATE':
                        $ids = self::registerDateFilter($setting, $ids);
                        break;
                    case 'DONT_RECEIVE':
                        $ids = self::dontReceiveRecentOrdersFilter($setting, $ids);
                        break;
                    case 'ORDER_FINISH':
                        $ids = self::quantityOrdersFinishedFilter($setting, $ids);
                        break;
                }

                if (count($ids) == 0) {
                    break;
                }
            }
        }

        $pivotData = array_fill(0, count($ids), ['fixed' => 0]);
        $syncData = array_combine($ids, $pivotData);
        $group->stores()->withPivotValue('fixed', 0)->sync($syncData);
    }

    protected static function categoriesFilter($categories): array
    {
        $stores = self::newStoresQuery();
        $stores->whereHas('categories_store', function ($query) use ($categories) {
            $query->whereIn('categoria_id', $categories);
        });

        return $stores->get()->pluck('id')->toArray();
    }

    protected static function registerDateFilter(GroupSettings $setting, $ids = null): array
    {
        $stores = self::newStoresQuery();
        if (!is_null($ids)) {
            $stores->whereIn('id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $stores->whereBetween('created_at', [$setting->date_min, $setting->date_max]);
                break;
            case 'MAIOR':
                $stores->whereDate('created_at', '>', $setting->date_min);
                break;
            case 'MENOR':
                $stores->whereDate('created_at', '<', $setting->date_max);
                break;
            case 'IGUAL':
                $stores->whereDate('created_at', $setting->date_min);
                break;
        }
        return $stores->get()->pluck('id')->toArray();
    }

    protected static function dontReceiveRecentOrdersFilter($setting, $ids)
    {
        $query = self::newStoresQuery()->selectRaw('date(max(pedidos.created_at)) as last_order')
            ->leftJoin('pedidos', 'pedidos.loja_id', '=', 'lojas.id')
            ->groupBy('lojas.id');

        if (!is_null($ids)) {
            $query->whereIn('lojas.id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $daysMin = now()->subDays($setting->quantity_min)->format('Y-m-d');
                $daysMax = now()->subDays($setting->quantity_max)->format('Y-m-d');
                $query->havingRaw("last_order > '{$daysMax}' and last_order < '{$daysMin}'");
                break;
            case 'MAIOR':
                $daysMin = now()->subDays($setting->quantity_min)->format('Y-m-d');
                $query->havingRaw("last_order < '{$daysMin}'");
                break;
            case 'MENOR':
                $daysMax = now()->subDays($setting->quantity_max)->format('Y-m-d');
                $query->havingRaw("last_order > '{$daysMax}'");
                break;
            case 'IGUAL':
                if ($setting->quantity_min > 0) {
                    $daysMin = now()->subDays($setting->quantity_min)->format('Y-m-d');
                    $query->havingRaw("last_order = '{$daysMin}'");
                } else {
                    $query->havingRaw("last_order is null");
                }
                break;
        }

        return $query->get()->pluck('id')->toArray();
    }

    protected static function quantityOrdersFinishedFilter(GroupSettings $setting, $ids = null): array
    {
        $query = self::newStoresQuery()->selectRaw('count(pedidos.id) as quantity')
            ->leftJoin('pedidos', 'pedidos.loja_id', '=', 'lojas.id')
            ->where('pedidos.estado', 'F')
            ->groupBy('lojas.id');

        if (!is_null($ids)) {
            $query->whereIn('lojas.id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $query->havingRaw('quantity > ? and quantity < ?', [$setting->quantity_min, $setting->quantity_max]);
                break;
            case 'MAIOR':
                $query->havingRaw('quantity > ' . $setting->quantity_min);
                break;
            case 'MENOR':
                $query->havingRaw('quantity < ' . $setting->quantity_max);
                break;
            case 'IGUAL':
                $query->havingRaw('quantity = ' . $setting->quantity_min);
                break;
        }

        return $query->get()->pluck('id')->toArray();
    }

    private static function newStoresQuery()
    {
        return Store::query()->where('habilitado', 1)->select('lojas.id');
    }
}