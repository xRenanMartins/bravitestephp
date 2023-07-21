<?php

namespace App\Http\Controllers;

use App\Rules\Category\V2\UpdateCategoryFranchise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Rules\Category\V2\StoreCategory;
use App\Rules\Category\V2\UpdateCategory;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Store;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;
use Packk\Core\Scopes\DomainScope;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('logger:store_category', ['only' => ['store']]);
        $this->middleware('logger:update_category', ['only' => ['update']]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasRole('admin-category|master')) {
            $request->merge(['is_primary' => 0]);
        }

        $finalQuery = Category::query()
            ->identic('id', $request->id)
            ->identic('tipo', $request->tipo)
            ->identic('ativo', $request->ativo)
            ->identic('is_primary', $request->is_primary)
            ->like('nome', $request->nome)
            ->orderBy('nome')->simplePaginate($request->length);

        return ['data' => $finalQuery, 'adminCategory' => $user->hasRole('admin-category')];
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->hasRole('admin-franchise|operator-franchise|admin-franchise-all')) {
            return response([
                'success' => false,
                'data' => null
            ]);
        } else {
            $payload = $request->validate([
                'vitrine' => 'required',
                'nome' => 'required',
                'ordem' => 'required',
                'ativo' => 'required',
                'imagem' => 'sometimes',
                'is_primary' => 'sometimes',
                'new_stores' => 'sometimes',
                'typeID' => 'sometimes',
                'groupID' => 'sometimes',
                'showcase_segment' => 'sometimes',
            ]);

            $resp = new StoreCategory();
            $response = $resp->execute($payload);

            return response([
                'success' => true,
                'data' => $response
            ]);
        }
    }

    public function edit(Request $request, $id)
    {
        $data = Category::find($id);
        $vitrinesAtivo = $data->showcase->ativo ?? 0;
        $resp = $data->toArray();
        $resp['vitrinesAtivo'] = $vitrinesAtivo;
        $resp['lojas'] = $data->categoriaLoja()->withoutGlobalScope(DomainScope::class)->get()->pluck('loja_id');

        return response([
            'success' => true,
            'data' => $resp
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        DB::table('categoria_loja')->where('categoria_id', $category->id)->delete();
        $category->update(['ativo' => 0]);
        $category->delete();

        return response([
            'success' => true
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->merge([
            'categoria_id' => $id
        ]);
        $user = Auth::user();

        $payload = $request->validate([
            'categoria_id' => 'required',
            'vitrine' => 'required',
            'new_stores' => 'sometimes',
            'remove_stores' => 'sometimes',
            'nome' => 'required',
            'ordem' => 'required',
            'ativo' => 'required',
            'imagem' => 'sometimes',
            'imagemName' => 'sometimes',
            'is_primary' => 'required',
            'groupID' => 'sometimes',
            'typeID' => 'sometimes',
            'showcase_segment' => 'sometimes',
        ]);

//        TODO: Rever essa lógica para franqueado
        if ($user->hasRole('admin-franchise|operator-franchise')) {
            $data = new UpdateCategoryFranchise();
            $response = $data->execute($payload);
        } else {
            $resp = new UpdateCategory();
            $response = $resp->execute($payload);
        }

        return response([
            'success' => true,
            'data' => $response
        ]);
    }

    public function storesToTarget(Request $request, $id)
    {
        if (isset($request->start) && isset($request->length)) {
            $total = $request->start / $request->length;
            $page = ($total + 1) > 0 ? ceil($total) + 1 : 1;
            $request->merge(['page' => $page]);
        }

        $stores = Store::query()
            ->join('categoria_loja', 'loja_id', '=', 'lojas.id')
            ->where('categoria_loja.categoria_id', $id)
            ->selectRaw('lojas.id, lojas.nome');

        if (!empty($request->outside)) {
            $stores->whereNotIn('lojas.id', explode(',', $request->outside));
        }

        if (!empty($request->search)) {
            $stores->where(function ($query) use ($request) {
                $query->where('lojas.nome', 'like', "%{$request->search}%")
                    ->orWhere('lojas.id', $request->search);
            });
        }

        return $stores->groupBy('lojas.id')->paginate($request->length);
    }

    public function storesToSource(Request $request, $id)
    {
        if (isset($request->start) && isset($request->length)) {
            $total = $request->start / $request->length;
            $page = ($total + 1) > 0 ? ceil($total) + 1 : 1;
            $request->merge(['page' => $page]);
        }
        $stores = Store::query()->selectRaw('id, nome, cnpj');

        if ($id > 0) {
            $categorias = DB::table('categoria_loja')->where('categoria_id', $id)
                ->get()->pluck('loja_id')->toArray();
            $stores->whereNotIn('id', $categorias);
        }

        if (!empty($request->outside)) {
            $stores->whereNotIn('id', explode(',', $request->outside));
        }

        if (!empty($request->search)) {
            $stores->where(function ($query) use ($request) {
                $query->where('nome', 'like', "%{$request->search}%")
                    ->orWhere('id', $request->search);
            });
        }

        $user = Auth::user();
        if (isset($user) && $user->isFranchiseOperator()) {
            $franchise = $user->getFranchise();
            if (!empty($franchise)) {
                $stores->where('franchise_id', $franchise->id);
            } else {
                $stores->whereNotNull('franchise_id');
            }
        }

        return $stores->orderBy('nome')->paginate($request->length);
    }

    public function showcases(Request $request)
    {
        return Showcase::query()->selectRaw('id, identifier')->get();
    }
}