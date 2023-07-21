<?php

namespace App\Rules\Store;

use Packk\Core\Models\Store;
use Packk\Core\Models\StoreActivity;
use Illuminate\Support\Facades\DB;

class ReproveStore
{
    /**
     * Reprovação de loja
     */
    public function execute($id)
    {
        try {
            DB::beginTransaction();
            $store = Store::findOrFail($id);

            $store->status = "REJECTED";
            $store->ativo = 0;
            $store->habilitado = 0;
            $store->save();

            $store->users->each(function($user) {
                $user->status = "INATIVO";
                $user->save();
            });

            $this->storeActivity($id);

            DB::commit();

            return ["id" => $store->id, "status" => $store->status];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private function storeActivity($id)
    {
        $storeActivity              = new StoreActivity();
        $storeActivity->user_id     = auth()->user()->id;
        $storeActivity->store_id    = $id;
        $storeActivity->description = "Recusa de loja";
        $storeActivity->activity    = 'DESABILITAR';

        $storeActivity->save();
    }
}