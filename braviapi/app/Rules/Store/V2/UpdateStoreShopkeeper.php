<?php

namespace App\Rules\Store\V2;

use Packk\Core\Models\Store;

class UpdateStoreShopkeeper
{
    public function execute($payload)
    {
        $store = Store::findOrFail($payload['id']);

        $user = $store->shopkeeper->user;
        $store->users()->detach([$user->id]);

        if (!$store->getSetting("store_has_not_shopkeeper", false)) {
            if (empty($store->getSetting("original_shopkeeper")) && $payload['is_vincule']) {
                $store->setSetting('original_shopkeeper', $store->lojista_id);
            } else if (!$payload['is_vincule']) {
                $store->setSetting('original_shopkeeper', null);
            }
        }

        $store->update($payload);
        $store->refresh();
        $user = $store->shopkeeper->user;

        $store->users()->syncWithoutDetaching([
                $user->id => [
                    "domain_id" => $store->domain_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]
        );

        $user->status = "ATIVO";
        $user->save();
    }
}