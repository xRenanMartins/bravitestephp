<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 15:15
 */

namespace App\Rules\Category;

use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\Storage;

class UpdateCategory
{
    public function execute($payload)
    {
        $categoria = new Category();

        if (isset($payload->domain_id) and !empty($payload->domain_id)) {
            $categoria->withoutGlobalScope('App\Scopes\DomainScope');
        }

        $categoria = $categoria->get_categoria_id($payload->categoria_id);

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

        $vitrine = Showcase::find($payload->vitrine_id);

        $categoria->ativo = $payload->ativo;
        $categoria->nome = $payload->nome;
        $categoria->vitrine_id = $payload->vitrine_id;
        $categoria->ordem = $payload->ordem;

        $categoria->save();

        $arrayLojasRequest = is_array($payload->lojas_categoria) ? $payload->lojas_categoria : explode(',', $payload->lojas_categoria);
        $lojas_atuais = \DB::table('categoria_loja')->where('categoria_id', $payload->categoria_id)->get();

        $lojas_id = [];

        foreach ($lojas_atuais as $loja) {
            array_push($lojas_id, $loja->loja_id);
        }

        $lojas_retiradas = null;
        if ($lojas_atuais != null) {
            $lojas_retiradas = array_diff($lojas_id, $arrayLojasRequest);

            foreach ($lojas_retiradas as $loja_id) {
                \DB::table('categoria_loja')->where('categoria_id', $payload->categoria_id)->where('loja_id', $loja_id)->delete();
            }
        }

        $lojas_adicionadas = array_diff($arrayLojasRequest, $lojas_id);

        foreach ($lojas_adicionadas as $loja_id) {
            if ($loja_id != "") {
                \DB::table('categoria_loja')->insert([
                    'categoria_id' => $payload->categoria_id, 'loja_id' => $loja_id
                ]);
            }
        }

        foreach ($lojas_retiradas as $loja_id) {
            $store = Store::find($loja_id);

            foreach ($store->categories_store()->get() as $category) {
                (new StoreCategorySync())->execute($store, "CREATEALL", $category);
            }

        }
        foreach ($lojas_adicionadas as $loja_id) {
            if ($loja_id != "") {

                $store = Store::find($loja_id);

                foreach ($store->categories_store()->get() as $category) {
                    (new StoreCategorySync())->execute($store, "CREATEALL", $category);
                }
            }
        }
        if (isset($vitrine->endereco)) {
            Store::whereIn("id", $lojas_adicionadas)->update(["type" => "PARCEIRO_LOCAL_NORMAL"]);
            Store::whereIn("id", $lojas_retiradas)->update(["type" => "PARCEIRO_NORMAL"]);
        }
        return $payload;
    }
}