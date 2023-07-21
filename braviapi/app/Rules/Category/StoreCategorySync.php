<?php

namespace App\Rules\Category;

use Packk\Core\Models\Mongo\Showcase;
use Packk\Core\Models\Mongo\Store;

class StoreCategorySync
{
    protected $store;

    public function execute($store, $action, $category)
    {
        if (env('SYNC_SHOPKEEPER', 0) == 0) return;
        if ($store) {
            $this->store = Store::where("reference_id", $store->id)->first();
        }
        switch ($action) {
            case "CREATE":
                $this->create($category);
                break;
            case "CREATEALL":
                $this->createAll($store);
                break;
            case "UPDATE":
                $this->update($category);
                break;
            case "DELETE":
                $this->delete($category);
                break;
        }
    }

    private function create($category)
    {
        $this->store->category()->create($this->preparePayloadSync($category))->save();
    }

    private function createAll($store)
    {
        $this->store->category()->delete();
        foreach ($store->categories_store()->get() as $category) {
            $this->create($category);
        }
    }

    private function update($model)
    {
        $showcases = Showcase::select("categories.$")->where("categories.reference_id", $model->id)->get();
        $stores = Store::select("category.$")->where("category.reference_id", $model->id)->get();

        $showcases->each(function ($showcase) use ($model) {
            $category = $showcase->categories->first();
            if (isset($category)) {
                $model->reference_id = $model->id;
                $category->fill($this->preparePayloadSync($model))->save();
            }
        });
        $stores->each(function ($store) use ($model) {
            $category = $store->category->first();
            if (isset($category)) {
                $category->fill($this->preparePayloadSync($model))->save();
            }
        });
    }

    private function delete($category)
    {
        $categoryMongo = $this->store->category()->where('reference_id', '=', $category->id)->first();
        $categoryMongo->delete();
    }

    private function preparePayloadSync($model)
    {
        return [
            "name" => $model->nome,
            "description" => $model->descricao ?? null,
            "type" => $model->tipo == "L" ? "LOJA" : "PRODUTO",
            "is_active" => $model->active,
            "image" => $model->imagem,
            "order" => $model->ordem,
            "created_at" => $model->created_at,
            "updated_at" => $model->updated_at,
            "reference_id" => $model->id
        ];
    }
}