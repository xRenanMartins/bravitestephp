<?php

namespace App\Rules\Group;

use Carbon\Carbon;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Group;
use Packk\Core\Models\GroupSettings;

class ProcessCustomerGroup
{
    public function execute(Group $group)
    {
        $settings = $group->settings;

        $ids = null;
        foreach ($settings as $setting) {
            switch ($setting->type) {
                case 'REGISTER_DATE':
                    $ids = self::registerDateFilter($setting, $ids);
                    break;
                case 'BIRTHDAY':
                    $ids = self::birthdayFilter($setting, $ids);
                    break;
                case 'DONT_ORDER':
                    $ids = self::dontSendOrderFilter($setting, $ids);
                    break;
                case 'ORDER_FINISH':
                    $ids = self::quantityOrdersFinishedFilter($setting, $ids);
                    break;
            }

            if (count($ids) == 0) {
                break;
            }
        }

        $pivotData = array_fill(0, count($ids), ['fixed' => 0]);
        $syncData = array_combine($ids, $pivotData);
        $group->clients()->withPivotValue('fixed', 0)->sync($syncData);
    }

    protected static function registerDateFilter(GroupSettings $setting, $ids = null): array
    {
        $customers = self::newCustomersQuery();
        if (!is_null($ids)) {
            $customers->whereIn('id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $customers->whereBetween('created_at', [$setting->date_min, $setting->date_max]);
                break;
            case 'MAIOR':
                $customers->whereDate('created_at', '>', $setting->date_min);
                break;
            case 'MENOR':
                $customers->whereDate('created_at', '<', $setting->date_max);
                break;
            case 'IGUAL':
                $customers->whereDate('created_at', $setting->date_min);
                break;
        }
        return $customers->get()->pluck('id')->toArray();
    }

    protected static function birthdayFilter(GroupSettings $setting, $ids = null): array
    {
        $customers = self::newCustomersQuery();

        if (!is_null($ids)) {
            $customers->whereIn('id', $ids);
        }

        $customers->whereHas('user', function ($query) use ($setting) {
            $query->whereNotNull('borned_at');
            switch ($setting->comparator) {
                case 'ENTRE':
                    $query->whereBetween('borned_at', [$setting->date_min, $setting->date_max]);
                    break;
                case 'MAIOR':
                    $query->whereDate('borned_at', '>', $setting->date_min);
                    break;
                case 'MENOR':
                    $query->whereDate('borned_at', '<', $setting->date_max);
                    break;
                case 'IGUAL':
                    $query->whereDate('borned_at', $setting->date_min);
                    break;
            }
            return $query;
        });


        return $customers->get()->pluck('id')->toArray();
    }

    protected static function dontSendOrderFilter(GroupSettings $setting, $ids = null): array
    {
        $query = self::newCustomersQuery()->selectRaw('date(max(pedidos.created_at)) as last_order')
            ->leftJoin('pedidos', 'pedidos.cliente_id', '=', 'clientes.id')
            ->groupBy('clientes.id');

        if (!is_null($ids)) {
            $query->whereIn('clientes.id', $ids);
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
        $query = self::newCustomersQuery()->selectRaw('count(pedidos.id) as quantity')
            ->leftJoin('pedidos', 'pedidos.cliente_id', '=', 'clientes.id')
            ->groupBy('clientes.id');

        if (!is_null($ids)) {
            $query->whereIn('clientes.id', $ids);
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

    private static function newCustomersQuery()
    {
        return Customer::query()->where('clientes.banido', 0)
            ->where('clientes.ativo', 1)->select('clientes.id');
    }
}