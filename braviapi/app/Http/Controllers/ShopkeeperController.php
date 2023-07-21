<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Role;
use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Store;
use Packk\Core\Util\Phones;

class ShopkeeperController extends Controller
{
    public function searchByEmail(Request $request)
    {
        $query = Shopkeeper::selectRaw('distinct lojistas.id, users.email')
            ->join('lojas', 'lojas.lojista_id', '=', 'lojistas.id')
            ->join('users', 'users.id', '=', 'lojistas.user_id')
            ->where('users.email', 'like', "%{$request->email}%");

        $user = Auth::user();
        if ($user->isFranchiseOperator()) {
            $query->whereNotNull('lojas.franchise_id');

            $franchise = $user->getFranchise();
            if (!empty($franchise)) {
                $query->where('lojas.franchise_id', $franchise->id);
            }
        }

        $data = $query->orderByDesc('id')->limit(10)->get();
        return ApiResponse::sendResponse($data);
    }

    public function searchByStore(Request $request)
    {
        $query = Store::select('id', 'nome', 'lojista_id')
            ->where('lojista_id', '<>', $request->lojista_id)
            ->when(!empty($request->name), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('id', $request->name)->orLike('nome', $request->name);
                });
            });

        $user = Auth::user();
        if ($user->isFranchiseOperator()) {
            $query->whereNotNull('lojas.franchise_id');

            $franchise = $user->getFranchise();
            if (!empty($franchise)) {
                $query->where('lojas.franchise_id', $franchise->id);
            }
        }

        $data = $query->orderByDesc('id')->limit(10)->get();
        return ApiResponse::sendResponse($data);
    }

    public function show(Request $request, $id)
    {
        try {
            $shopkeeper = Shopkeeper::with('user')->findOrFail($id);

            return ApiResponse::sendResponse([
                'email' => $shopkeeper->user->email,
                'responsible_mail' => $shopkeeper->email_proprietario,
                'phone' => $shopkeeper->user->telefone,
                'name' => $shopkeeper->user->nome,
                'last_name' => $shopkeeper->user->sobrenome,
            ]);
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Lojista não encontrado');
        }
    }

    public function getOriginal(Request $request, $id)
    {
        try {
            Cache::forget("store.{$id}.settings");
            $store = Store::findOrFail($id);
            return ApiResponse::sendResponse([
                'shopkeeper_id' => $store->getSetting("original_shopkeeper"),
                'not_has_shopkeeper' => $store->getSetting("store_has_not_shopkeeper"),
            ]);
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Loja não encontrada!');
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $store = Store::with('shopkeeper.user')->findOrFail($request->store_id);
            $store->setSetting("store_has_not_shopkeeper", null);

            $user = $store->shopkeeper->user;
            $store->users()->detach([$user->id]);

            try {
                $newUser = $user->replicate();
                $newUser->nome = $request->nomelojista;
                $newUser->sobrenome = $request->sobrenome;
                $newUser->email = $request->email;
                $newUser->password = bcrypt($request->senha);
                $newUser->telefone = Phones::format($request->telefone_lojista);
                $newUser->created_at = now();
                $newUser->domain_id = $store->domain_id;
                $newUser->save();
            } catch (\PDOException) {
                DB::rollBack();
                return ApiResponse::sendError('O email informado já está em uso por outro lojista.');
            }

            $role = Role::where("name", "owner")->first();
            $newUser->roles()->syncWithoutDetaching([$role->id => [
                'created_at' => now(),
                'updated_at' => now()
            ]]);

            $shopkeeper = $store->shopkeeper->replicate();
            $shopkeeper->user()->associate($newUser);
            $shopkeeper->email_proprietario = $request->email_proprietario;
            $shopkeeper->generatePromotionalCode();
            $shopkeeper->created_at = now();
            $shopkeeper->domain_id = $store->domain_id;
            $shopkeeper->save();
            $store->shopkeeper()->associate($shopkeeper);
            $store->save();

            $store->users()->syncWithoutDetaching([$newUser->id => [
                "domain_id" => $store->domain_id,
                'created_at' => now(),
                'updated_at' => now()
            ]]);
            DB::commit();

            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            DB::rollBack();
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($e);
        }
    }
}