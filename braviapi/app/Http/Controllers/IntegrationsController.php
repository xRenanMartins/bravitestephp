<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationsController extends Controller
{
    public function index(Request $request)
    {
        return DB::table('oauth_clients')
            ->when(!empty($request->search), function($query) use($request) {
                $query->where(function($query) use($request){
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('secret', 'like', "%{$request->search}%")
                        ->orWhere('reference_id', 'like', "%{$request->search}%")
                    ->orWhere('reference_provider', 'like', "%{$request->search}%");
                });
            })->select(['id', 'name', 'secret', 'reference_id', 'reference_provider', 'blacklist_hour'])
            ->simplePaginate($request->length);
    }

    public function update(Request $request, $id)
    {
        $oauth = DB::table('oauth_clients')->where('id',$id)->update([
            'name' => $request->name,
            'reference_id' => $request->reference_id,
            'reference_provider' => $request->reference_provider,
            'blacklist_hour' => $request->blacklist_hour,
        ]);

        return [
            'message' => 'Atualizado com sucesso!'
        ];
    }
}