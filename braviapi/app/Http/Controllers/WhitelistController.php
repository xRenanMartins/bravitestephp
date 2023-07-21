<?php

namespace App\Http\Controllers;

use App\Rules\Showcase\SaveWhitelist;
use Illuminate\Http\Request;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\CustomerGroup;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Whitelist;
use Packk\Core\Models\Store;

class WhitelistController extends Controller
{
    public function index(Request $request, $id)
    {
        if(isset($request->store_id)){
            $data = Store::find($id);
        }else{
            $data = Showcase::find($id);
        }
        
        $result = $data->whitelists()
            ->join('clientes', 'clientes.id', '=', 'whitelists.customer_id')
            ->join('users', 'users.id', '=', 'clientes.user_id')
            ->selectRaw('whitelists.*, CONCAT(users.nome, " ", users.sobrenome) as client_desc');

        if (!empty($request->search)) {
            $result->where(function($q) use($request) {
                $q->where('clientes.id', $request->search)
                    ->orWhere('users.nome', 'like', "{$request->search}%")
                    ->orWhere('users.sobrenome', 'like', "{$request->search}%");
            });
        }

        return $result->simplePaginate(10);
    }

    public function groups(Request $request)
    {
        $groupRFM = CustomerGroup::identic('domain_id', $request->domain_id)->orderBy('title', 'asc')->get();
        return [
            'rfm' => $groupRFM,
        ];
    }

    public function store(Request $request, $id, SaveWhitelist $saveWhitelist)
    {
        if(isset($request->store_id)){
            $store = Store::query()->findOrFail($id);
            if ($request->action === 'replace') {
                Whitelist::where('store_id', $store->id)->where('type', 'STORE')->forceDelete();
            }
            return $saveWhitelist->execute($store, $request->all());
        }else{
            $showcase = Showcase::query()->findOrFail($id);
            if ($request->action === 'replace') {
                Whitelist::where('showcase_id', $showcase->id)->where('type', 'SHOWCASE')->forceDelete();
            }
            return $saveWhitelist->execute($showcase, $request->all());
        }
    }

    public function update(Request $request, $id)
    {
        $data = Whitelist::find($id);
        $data->status = $request->status;
        $data->save();
        if (!empty($data->store_id)) {
            dispatch(new SendShopFeedEvent($data->store_id, 'store:whitelist:update'));
        }

        return response()->json($data);
    }

    public function destroy($id)
    {
        $data = Whitelist::find($id);
        $data->forceDelete();

        if (!empty($data->store_id)) {
            dispatch(new SendShopFeedEvent($data->store_id, 'store:whitelist:update'));
        }
        return response()->json(['success' => true]);
    }

    public function destroyAll($id, Request $request)
    {
        if(isset($request->store_id)){
            $store = Store::query()->findOrFail($id);
            Whitelist::where('store_id', $store->id)->where('type', 'STORE')->forceDelete();
            dispatch(new SendShopFeedEvent($store->id, 'store:whitelist:update'));
            return response()->json(['success' => true]);
        }else{
            $showcase = Showcase::query()->findOrFail($id);
            Whitelist::where('showcase_id', $showcase->id)->where('type', 'SHOWCASE')->forceDelete();
            return response()->json(['success' => true]);
        }
    }
}