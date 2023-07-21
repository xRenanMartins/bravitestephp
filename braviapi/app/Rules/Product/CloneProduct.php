<?php

/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-08-03
 * Time: 16:10
 */

namespace App\Rules\Product;

use Packk\Core\Models\Store;
use Packk\Core\Models\Category;
use Packk\Core\Jobs\Products\CategoryProductClone;
use Packk\Core\Jobs\Products\GroupInputsClone;

class CloneProduct
{
    public function execute($payload)
    {
        $originalStore = Store::findOrFail($payload->loja_id_original);
        $newStore = Store::findOrFail($payload->loja_id);

        foreach ($originalStore->categories as $originalCategory) {
            $newCategory = new Category();
            $newCategory->nome = $originalCategory->nome;
            $newCategory->referencia_id = $originalCategory->referencia_id;
            $newCategory->referencia_fornecedor = $originalCategory->referencia_fornecedor;
            $newCategory->domain_id = $originalCategory->domain_id;

            $newCategory->loja_id = $newStore->id;
            $newCategory->save();

            dispatch(new CategoryProductClone($originalCategory->products, $newCategory->id, $newStore, $originalStore));
        }

        return ['success' => true];
    }
}
