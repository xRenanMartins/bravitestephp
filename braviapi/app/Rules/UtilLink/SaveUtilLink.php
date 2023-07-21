<?php

namespace App\Rules\UtilLink;

use Packk\Core\Models\UtilLink;
use Packk\Core\Models\UtilLinkStore;

class SaveUtilLink
{
    public function execute($payload)
    {
        try {
            $link = isset($payload["id"]) ? UtilLink::findOrFail($payload["id"]) : new UtilLink();
            $link->name = $payload["name"];
            $link->description = $payload["description"];
            $link->uri = $payload["uri"];
            $link->save();

            if (!empty($payload["lojas"])) {
                UtilLinkStore::where("util_link_id", $link->id)->delete();
                foreach ($payload["lojas"] as $loja_id) {
                    $link_store = new UtilLinkStore();
                    $link_store->store_id = $loja_id;
                    $link_store->util_link_id = $link->id;
                    $link_store->save();
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
