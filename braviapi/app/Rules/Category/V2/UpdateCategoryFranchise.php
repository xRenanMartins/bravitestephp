<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 15:15
 */

namespace App\Rules\Category\V2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;
use Packk\Core\Models\Store;
use Packk\Core\Models\UserFranchise;

class UpdateCategoryFranchise
{
    public function execute($payload)
    {
        $user = Auth::user();
        $userFranchisesIDS = UserFranchise::query()->where('user_id', '=', $user->id)->pluck('franchise_id');
        $storesFranchiseIDS = Store::query()->whereIn('franchise_id', $userFranchisesIDS)->pluck('id');

        $deleted = DB::table('categoria_loja')
            ->where('categoria_id', '=', $payload['categoria_id'])
            ->whereIn('loja_id', $storesFranchiseIDS)
            ->delete();

        foreach ($payload['lojas_categoria'] as $store) {
            if ($store != "") {
                DB::table('categoria_loja')->insert([
                    'categoria_id' => $payload["categoria_id"], 'loja_id' => $store
                ]);
            }
        }

        return $deleted;
    }
}