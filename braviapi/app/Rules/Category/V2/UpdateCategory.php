<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 15:15
 */

namespace App\Rules\Category\V2;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UpdateCategory
{
    private $payload;
    private $category;
    public function execute($payload)
    {
        $this->payload = $payload;
        try {
            DB::beginTransaction();
            $this->category = Category::where('id', $this->payload['categoria_id'])->first();

            $user = Auth::user();
            $showcase = Showcase::find($this->payload["vitrine"]);

            $this->category->ativo = $this->payload["ativo"];
            $this->category->nome = $this->payload["nome"];
            $this->category->vitrine_id = $this->payload["vitrine"];
            $this->category->ordem = $this->payload["ordem"];
            $this->category->showcase_segment = $this->payload["showcase_segment"];

            if ($user->hasRole('admin-category')) {
                $this->category->is_primary = $this->payload["is_primary"] ?? false;
            } else {
                $this->category->is_primary = false;
            }
            $this->category->save();

            // Adicionando lojas via pick
            $newStores = $this->payload["new_stores"];
            if ($newStores && count($newStores) > 0) {
                $this->category->stores()->attach($newStores);
            }

            // Removendo lojas via pick
            $removeStores = $this->payload["remove_stores"];
            if ($removeStores && count($removeStores) > 0) {
                $this->category->stores()->detach($removeStores);
            }

            // Adicionando ou substituindo via input
            if (isset($this->payload['typeID']) && in_array($this->payload['typeID'], ['adicionar', 'substituir'])) {

                // Caso seja substituir remove todos os anteriores
                if ($this->payload['typeID'] == "substituir") {
                    $storesIds = Category::find($this->payload['categoria_id'])->stores()->pluck('loja_id')->toArray();

                    if (isset($showcase->endereco)) {
                        Store::whereIn("id", $storesIds)->update(["type" => "PARCEIRO_NORMAL"]);
                    }

                    Category::find($this->payload['categoria_id'])->stores()->detach();
                }

                $newStores = [];
                $array = $this->payload['new_stores'];
                if (isset($this->payload['groupID'])) {
                    $array = $this->payload['groupID'];
                }
                foreach ($array as $storeId) {
                    $firstCategory = DB::table('categoria_loja')
                        ->where('categoria_id', $this->payload['categoria_id'])
                        ->where('loja_id', $storeId)
                        ->exists();

                    if (!$firstCategory) {
                        $newStores[] = $storeId;
                        DB::table('categoria_loja')->insert([
                            'categoria_id' => $this->payload['categoria_id'], 'loja_id' => $storeId
                        ]);
                    }
                }
            }

            if (isset($showcase->endereco)) {
                if ($newStores && count($newStores) > 0) {
                    Store::whereIn("id", $newStores)->update(["type" => "PARCEIRO_LOCAL_NORMAL"]);
                }
                if ($removeStores && count($removeStores) > 0) {
                    Store::whereIn("id", $removeStores)->update(["type" => "PARCEIRO_NORMAL"]);
                }
            }

            DB::commit();
            $this->updateNear();

            return $this->category;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function updateImage()
    {
        if (isset($this->payload["imagem"])) {
            if (!empty($this->payload["imagemName"])) {
                $image = $this->payload["imagem"];
                $base64_str = substr($image, strpos($image, ",") + 1);
                $base = base64_decode($base64_str);

                $name = time() . $this->payload["imagemName"];
                Storage::put('categories/' . $name, $base, 'public');
                $imagem = Storage::url('categories/' . $name);
                $this->category->imagem = $imagem;
            }
        }
    }

    private function updateNear() {
        dispatch(function () {
            Artisan::call("category:updated {$this->category->id}");
        });
    }
}