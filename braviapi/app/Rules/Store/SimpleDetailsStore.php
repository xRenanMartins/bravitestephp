<?php

namespace App\Rules\Store;

use Packk\Core\Models\Category;
use Packk\Core\Models\CategoryStore;
use Packk\Core\Models\Mongo\StoreFeed;
use Packk\Core\Models\Store;

class SimpleDetailsStore
{
    public function execute($id)
    {
        $store = Store::with('address')->findOrFail($id);

        $isMarketplace = in_array($store->type, ['PARCEIRO_MARKETPLACE_NORMAL', 'PARCEIRO_MARKETPLACE_EXCLUSIVO', 'NAO_PARCEIRO']);

        $hasHour = $store->horarios()->where("tipo", "NORMAL")->count() > 0;
        $hasShowcase = CategoryStore::query()->where('loja_id', $id)->exists();

        if ($isMarketplace) {
            $shopFeed = StoreFeed::query()->select('rules')->where('id', (int)$id)->first();
            $hasZone = $shopFeed->rules['service_area']['is_active'];
        } else {
            $hasZone = StoreFeed::query()->whereNotNull('zone_id')->where('id', (int)$id)->exists();
        }

        return [
            'status' => $store->status,
            'shopkeeper_id' => $store->lojista_id,
            'is_franchise' => !empty($store->franchise_id),
            'active' => $store->habilitado && $store->ativo,
            'has_seller' => !empty($store->zoop_seller_id),
            'has_hour' => $hasHour,
            'is_market' => $store->is_market,
            'has_product' => $store->products()->count() > 0,
            'has_payments' => $store->payment_methods()->count() > 0,
            'has_zone' => $hasZone,
            'has_showcase' => $hasShowcase,
            'address' => isset($store->address)
                ? $store->address->only(['endereco', 'numero', 'bairro', 'cidade', 'state', 'complemento', 'cep'])
                : null
        ];
    }
}
