<?php

namespace App\Rules\Product;

use Packk\Core\Models\Store;
use Packk\Core\Models\Product;
use Illuminate\Support\Facades\DB;

class CloneProductImages
{
    public function execute($payload)
    {
        $store_origin = Store::findOrFail($payload->store_id_origin);
        $store_target = Store::findOrFail($payload->store_id_target);

        // SELECT
        //     p.id, p.nome, p.imagem_s3, _p.imagem_s3
        // FROM
        // produtos p
        // INNER JOIN
        // produtos _p ON p.ean = _p.ean
        // WHERE
        //     p.categoria_id IN (SELECT id FROM categorias WHERE loja_id = 5673) -- PARA Jacuhy Alphaville
        // AND _p.categoria_id IN (SELECT id FROM categorias WHERE loja_id = 3863) -- DE Praia do Canto - Posto Iate
        // AND p.imagem_s3 IS NULL AND _p.imagem_s3 IS NOT NULL;

        Product::join("produtos as origin", "origin.ean", "produtos.ean")
            ->select("produtos.id", "produtos.nome", "origin.imagem_s3 as img_origin", "produtos.imagem_s3 as img_target")
            ->where("produtos.store_id", $payload->store_id_target)
            ->where("origin.store_id", $payload->store_id_origin)
            ->whereNull("produtos.imagem_s3")
            ->whereNotNull("origin.imagem_s3")
            ->groupBy("produtos.ean")
            ->update(["produtos.imagem_s3" => DB::raw("origin.imagem_s3")]);

        return ['success' => true];
    }
}
