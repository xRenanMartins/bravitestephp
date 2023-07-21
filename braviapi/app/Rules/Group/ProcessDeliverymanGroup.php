<?php

namespace App\Rules\Group;

use Carbon\Carbon;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\Group;
use Packk\Core\Models\GroupSettings;

class ProcessDeliverymanGroup
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
                case 'DONT_DELIVER':
                    $ids = self::dontDeliverOrderFilter($setting, $ids);
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
        $group->deliveries()->withPivotValue('fixed', 0)->sync($syncData);
    }

    protected static function registerDateFilter(GroupSettings $setting, $ids = null)
    {
        $deliverymen = self::newDeliverymanQuery();

        if (!is_null($ids)) {
            $deliverymen->whereIn('id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $deliverymen->whereBetween('created_at', [$setting->date_min, $setting->date_max]);
                break;
            case 'MAIOR':
                $deliverymen->whereDate('created_at', '>', $setting->date_min);
                break;
            case 'MENOR':
                $deliverymen->whereDate('created_at', '<', $setting->date_max);
                break;
            case 'IGUAL':
                $deliverymen->whereDate('created_at', $setting->date_min);
                break;
        }
        return $deliverymen->get()->pluck('id')->toArray();
    }

    protected static function birthdayFilter(GroupSettings $setting, $ids = null): array
    {
        $deliverymen = self::newDeliverymanQuery();

        if (!is_null($ids)) {
            $deliverymen->whereIn('id', $ids);
        }

        $deliverymen->whereHas('user', function ($query) use ($setting) {
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


        return $deliverymen->get()->pluck('id')->toArray();
    }

    protected static function dontDeliverOrderFilter(GroupSettings $setting, $ids = null)
    {
        $query = self::newDeliverymanQuery()->selectRaw('date(max(entregas.created_at)) as last_deliver')
            ->leftJoin('entregas', 'entregas.entregador_id', '=', 'entregadores.id')
            ->groupBy('entregadores.id');

        if (!is_null($ids)) {
            $query->whereIn('entregadores.id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $daysMin = now()->subDays($setting->quantity_min)->format('Y-m-d');
                $daysMax = now()->addDays($setting->quantity_max)->format('Y-m-d');
                $query->havingRaw("last_deliver > '{$daysMax}' and last_deliver < '{$daysMin}'");
                break;
            case 'MAIOR':
                $daysMin = now()->subDays($setting->quantity_min)->format('Y-m-d');
                $query->havingRaw("last_deliver < '{$daysMin}'");
                break;
            case 'MENOR':
                $daysMax = now()->addDays($setting->quantity_max)->format('Y-m-d');
                $query->havingRaw("last_deliver > '{$daysMax}'");
                break;
            case 'IGUAL':
                if ($setting->quantity_min > 0) {
                    $daysMin = now()->subDays($setting->quantity_min)->format('Y-m-d');
                    $query->havingRaw("last_deliver = '{$daysMin}'");
                } else {
                    $query->havingRaw("last_deliver is null");
                }
                break;
        }

        return $query->get()->pluck('id')->toArray();
    }

    protected static function quantityOrdersFinishedFilter(GroupSettings $setting, $ids = null): array
    {
        $query = self::newDeliverymanQuery()->selectRaw('count(pedidos.id) as quantity')
            ->leftJoin('entregas', 'entregas.entregador_id', '=', 'entregadores.id')
            ->leftJoin('pedidos', 'entregas.pedido_id', '=', 'pedidos.id')
            ->groupBy('entregadores.id');

        if (!is_null($ids)) {
            $query->whereIn('entregadores.id', $ids);
        }

        switch ($setting->comparator) {
            case 'ENTRE':
                $query->havingRaw("quantity > {$setting->quantity_min} and quantity < {$setting->quantity_max}");
                break;
            case 'MAIOR':
                $query->havingRaw("quantity > {$setting->quantity_min}");
                break;
            case 'MENOR':
                $query->havingRaw("quantity < {$setting->quantity_max}");
                break;
            case 'IGUAL':
                $query->havingRaw("quantity = {$setting->quantity_min}");
                break;
        }

        return $query->get()->pluck('id')->toArray();
    }

    private static function newDeliverymanQuery()
    {
        return Deliveryman::query()->whereRaw("entregadores.estado = 'A'")
            ->whereRaw('entregadores.banido = 0')->select('entregadores.id');
    }
}