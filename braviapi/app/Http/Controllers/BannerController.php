<?php

namespace App\Http\Controllers;

use App\Utils\Files;
use Illuminate\Http\Request;
use Packk\Core\Models\Banner;
use Packk\Core\Models\Store;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        return Banner::query()
            ->identic('active', $request->active)
            ->like('banners.title', $request->title)
            ->orderByDesc('created_at')
            ->simplePaginate($request->length);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'title' => 'required',
            'description' => 'nullable',
            'badge_texto' => 'nullable',
            'badge_texto_cor' => 'nullable',
            'badge_background' => 'nullable',
            'active' => 'nullable',
            'loja_id' => 'required',
            'lojas' => 'required',
        ]);

        if (!empty($request->imagemName)) {
            $payload['url'] = Files::saveFromBase64($request->imagem, 'banners/', $request->imagemName);
        }

        $data = Banner::create($payload);
        $data->stores()->sync($request->lojas, [
            'created_at' => now(),
            'updated_at' => now(),
            'domain_id' => $data->domain_id
        ]);
        return response([
            'success' => true,
            'data' => $data
        ]);
    }

    public function edit(Request $request, $id)
    {
        $data = Banner::find($id);
        $resp = $data->toArray();

        $store = Store::query()
            ->selectRaw('CONCAT(id, " - ", nome) as store')->find($data->loja_id);
        $resp['store'] = $store->store ?? '';

        $stores = Store::query()
            ->whereIn('id', $data->store_banner->pluck('loja_id')->toArray())
            ->selectRaw('CONCAT(id, " - ", nome) as store')
            ->orderBy('nome')->get()->pluck('store')->toArray();
        $resp['stores'] = $stores;

        return response([
            'success' => true,
            'data' => $resp
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $request->validate([
            'title' => 'required',
            'description' => 'nullable',
            'badge_texto' => 'nullable',
            'badge_texto_cor' => 'nullable',
            'badge_background' => 'nullable',
            'active' => 'nullable',
            'loja_id' => 'required',
            'lojas' => 'required',
        ]);

        if (!empty($request->imagemName)) {
            $payload['url'] = Files::saveFromBase64($request->imagem, 'banners/', $request->imagemName);
        }

        $data = Banner::find($id);
        $data->update($payload);
        $data->stores()->sync($request->lojas, [
            'created_at' => now(),
            'updated_at' => now(),
            'domain_id' => $data->domain_id
        ]);

        return response([
            'success' => true,
            'data' => $data
        ]);
    }

    public function stores(Request $request)
    {
        return Store::query()
            ->where(function($q) use($request) {
                $q->identic('id', $request->search)->orLike('nome', $request->search);
            })->selectRaw('CONCAT(id, " - ", nome) as store')
            ->limit(10)->orderBy('nome')->get()->pluck('store')->toArray();
    }
}