<?php

namespace App\Rules\Order;

use Packk\Core\Models\Order;
use Packk\Core\Models\Domain;
use Packk\Core\Integration\Orders\SendPrintOrder;
use Packk\Core\Integration\ConnectDez\OrderSapore;

class AutomaticApprove
{
    public function execute($id)
    {
        try {
            $order = Order::withoutGlobalScope('App\Scopes\DomainScope')->findOrFail($id);
            $automaticAccept = $order->loja->automatic_accept;
            if(isset($automaticAccept) && $order->estado == 'R') {
                $order->estado                    = 'A';
                $order->metrica->preparation_time = $automaticAccept;
                $order->metrica->aproved_at       = now();
                $order->getDistance();

                $domain     = Domain::find($order->domain_id);
                $connectDez = $domain->hasFeature('connect_dez');
    
                if($connectDez) {
                    try {
                        $idSapore = OrderSapore::approveOrder($order, $order->reference_id);
                        $order->reference_id = $idSapore;
                    } catch (\Throwable $th) {}
                }
    
                $order->save();
    
                // Notification printer
                $shopkeeperId = $order->loja->lojista_id;
                $userId       = null;
                $type         = "print";

                dispatch(new SendPrintOrder($order, $shopkeeperId, $userId, $type));

            }
            return $order;
        } catch (\Throwable $th) {
            app('sentry')->captureException($th);
        }
    }
}