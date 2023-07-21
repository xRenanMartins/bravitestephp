<?php

namespace App\Rules\Store;

use App\Response\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\Ban;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;

class UnbanDeliveryman
{
    public function execute($storeId, $deliverymanId)
    {
        $store = Store::findOrFail($storeId);
        $deliveryman = Deliveryman::findOrFail($deliverymanId);

        Ban::withoutGlobalScope(DomainScope::class)->where('entregador_id', $deliveryman->id)->where('loja_id', $store->id)->delete();
        $this->unbanShipperStoreActivity($store, $deliveryman);
    }

    private function unbanShipperStoreActivity(Store $store, Deliveryman $deliveryman)
    {
        $admin = Auth::user();
        $context = [
            '[::operador]' => $admin->nome . ' ' . $admin->sobrenome,
            '[::shipper]' => "{$deliveryman->user->nome} {$deliveryman->user->sobrenome}",
            '[::loja]' => $store->nome
        ];

        $deliveryman->user->addAtividade('ADMIN_STORE_UNBAN_SHIPPER', $context, $admin->id, 'ADMIN');
    }
}