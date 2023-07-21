<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 15:37
 */

namespace App\Rules\Category\V2;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;
use Packk\Core\Models\Store;

class StoreCategory
{
    public function execute($payload)
    {
        try {
            DB::beginTransaction();

            $category = new Category();
            $user = Auth::user();

            $category->ativo = $payload["ativo"];
            $category->nome = $payload["nome"];
            $category->vitrine_id = $payload["vitrine"];
            $category->showcase_segment = $payload["showcase_segment"];
            $category->tipo = 'L';
            $showcase = Showcase::find($payload["vitrine"]);

            if (!empty($payload["ordem"])) {
                $category->ordem = $payload["ordem"];
            }
            if ($user->hasRole('admin-category')) {
                $category->is_primary = $payload["is_primary"] ?? false;
            } else {
                $category->is_primary = false;
            }

            if (isset($payload["imagem"])) {
                if (!empty($payload["imagemName"])) {
                    $image = $payload["imagem"];
                    $base64_str = substr($image, strpos($image, ",") + 1);
                    $base = base64_decode($base64_str);

                    $name = time() . $payload["imagemName"];
                    Storage::put('categories/' . $name, $base, 'public');
                    $imagem = Storage::url('categories/' . $name);
                    $category->imagem = $imagem;
                }
            }
            $category->save();

            $newStores = $payload["new_stores"];
            if (is_array($newStores)) {
                $category->stores()->attach($newStores);
            }

            if (isset($payload['typeID'])) {
                if ($payload['typeID'] == "adicionar") {
                    $newStores = [];

                    foreach ($payload['groupID'] as $storeId) {
                        $firstCategory = DB::table('categoria_loja')
                            ->where('categoria_id', $category->id)
                            ->where('loja_id', $storeId)
                            ->exists();

                        if (!$firstCategory) {
                            $newStores[] = $storeId;
                            DB::table('categoria_loja')->insert([
                                'categoria_id' => $category->id, 'loja_id' => $storeId
                            ]);
                        }
                    }
                }
            }

            if (isset($showcase->endereco)) {
                Store::whereIn("id", $newStores)->update(["type" => "PARCEIRO_LOCAL_NORMAL"]);
            }

            DB::commit();

            dispatch(function () use($category) {
                Artisan::call("category:updated {$category->id}");
            });
            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}