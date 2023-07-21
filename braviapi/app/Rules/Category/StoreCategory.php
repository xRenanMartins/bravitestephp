<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 15:37
 */

namespace App\Rules\Category;

use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\Storage;

class StoreCategory
{
    public function execute($payload)
    {
        $categoria = new Category();

        if (isset($payload->domain_id) and !empty($payload->domain_id)) {
            $categoria->withoutGlobalScope('App\Scopes\DomainScope');
        }

        $categoria->ativo = $payload->ativo;
        $categoria->nome = $payload->nome;
        $categoria->vitrine_id = $payload->vitrine;
        $categoria->tipo = 'L';
        $vitrine = Showcase::find($payload->vitrine);
        if ($payload->ordem != null) {
            $categoria->ordem = $payload->ordem;
        }

        if (isset($payload->imagem)) {
            if (!empty($payload->imagemName)) {
                $name = time() . $payload->imagemName;
                Storage::put('categories/' . $name, $payload->imagem, 'public');
                $imagem = Storage::url('categories/' . $name);
                $categoria->imagem = $imagem;
            } else {
                $uname = time() . rand() . '.' . $payload->imagem->getClientOriginalExtension();
                $importDir = base_path('media/categoria/');
                $payload->imagem->move($importDir, $uname);
                $imagem = \Image::make(base_path('media/categoria/' . $uname));
                Storage::put('categories/' . $uname, $imagem->stream()->__toString());
                $categoria->imagem = Storage::url('categories/' . $uname);
            }
        }

        $validator = $categoria->save();

        if (!empty($payload->lojas_categoria)) {
            $arrayLojas = is_array($payload->lojas_categoria) ? $payload->lojas_categoria : explode(',', $payload->lojas_categoria);

            foreach ($arrayLojas as $loja_id) {
                \DB::table('categoria_loja')->insert([
                    'categoria_id' => $categoria->id, 'loja_id' => $loja_id
                ]);
            }

            foreach ($arrayLojas as $loja_id) {
                $store = Store::find($loja_id);
                (new StoreCategorySync())->execute($store, "CREATE", $categoria);
            }
            if (isset($vitrine->endereco)) {
                Store::whereIn("id", $arrayLojas)->update(["type" => "PARCEIRO_LOCAL_NORMAL"]);
            }
        }
        return $validator;
    }
}