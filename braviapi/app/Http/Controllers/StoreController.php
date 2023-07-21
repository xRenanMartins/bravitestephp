<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store\StoreIndexRequest;
use App\Response\ApiResponse;
use App\Rules\Concierge\V2\StoreConcierge;
use App\Rules\Concierge\V2\UpdateConcierge;
use App\Rules\Product\CloneProduct;
use App\Rules\Store\ApproveStore;
use App\Rules\Store\CitiesAutocomplete;
use App\Rules\Store\BanDeliveryman;
use App\Rules\Store\CloneStore;
use App\Rules\Store\GetConciergeEdit;
use App\Rules\Store\GetOptionsDomain;
use App\Rules\Store\GetStoreEdit;
use App\Rules\Store\ListStores;
use App\Rules\Store\ReproveStore;
use App\Rules\Store\SimpleDetailsStore;
use App\Rules\Store\Stores;
use App\Rules\Store\UnbanDeliveryman;
use App\Rules\Store\CreateStore;
use App\Rules\Store\UpdateStore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Packk\Core\Actions\Admin\Product\StoreReflect;
use Packk\Core\Actions\Admin\Store\CreateStore as ExternalCreateStore;
use Packk\Core\Actions\Admin\User\DeleteUser;
use Packk\Core\Events\UserLoggedOut;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Ban;
use Packk\Core\Models\Category;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\Property;
use Packk\Core\Models\Reason;
use Packk\Core\Models\Retention;
use Packk\Core\Models\ScheduledAction;
use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Store;
use Packk\Core\Models\StoreActivity;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Util\Pusher;
use PHPUnit\Exception;

class StoreController extends Controller
{
    public function index(StoreIndexRequest $request, ListStores $listStores)
    {
        try {
            $request->validated();
            $data = $listStores->execute($request);
            return ApiResponse::sendResponse($data);
        } catch (Exception $exception) {
            return ApiResponse::sendUnexpectedError($exception);
        }
    }

    public function indexDetails(Request $request, $id, SimpleDetailsStore $detailsStore)
    {
        try {
            $data = $detailsStore->execute($id);
            return ApiResponse::sendResponse($data);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::sendError('Loja não encontrada', 404);
        } catch (Exception $exception) {
            return ApiResponse::sendUnexpectedError($exception);
        }
    }

    public function citiesAutocomplete(Request $request, CitiesAutocomplete $autocomplete)
    {
        try {
            $data = $autocomplete->execute($request);
            return response()->json($data);
        } catch (Exception $exception) {
            return ApiResponse::sendUnexpectedError($exception);
        }
    }

    public function optionsDomain(Request $request, GetOptionsDomain $getOptionsDomain)
    {
        $payload = $this->validate($request, ["domain_id" => "required", "cloneStore" => "sometimes", "concierge" => "sometimes"]);
        return $getOptionsDomain->execute($payload);

    }

    public function edit($id, GetStoreEdit $getStoreEdit)
    {
        try {
            return $getStoreEdit->execute($id);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function store(Request $request, CreateStore $createStore)
    {
        $payload = $this->payload($request);
        return response()->json($createStore->execute($payload));
    }

    public function update(Request $request, UpdateStore $updateStore)
    {
        $payload = $this->payload($request);
        return response()->json($updateStore->execute($payload));
    }

    public function update_concierge(Request $request, UpdateConcierge $updateConcierge)
    {
        return response()->json($updateConcierge->execute($request));
    }

    public function create_concierge(Request $request, StoreConcierge $storeConcierge)
    {
        $payload = $this->payload($request);
        return response()->json($storeConcierge->execute($request));
    }

    public function edit_concierge($id, GetConciergeEdit $getConciergeEdit)
    {
        return $getConciergeEdit->execute($id);
    }

    public function clone_concierge(Request $request, StoreReflect $storeReflect, CloneStore $cloneStore)
    {
        $payload = $this->payload($request);
        return response()->json($cloneStore->execute($payload, $storeReflect));
    }

    public function recess(Request $request)
    {
        return Stores::recess($request);
    }

    public function wall(Request $request)
    {
        try {
            DB::beginTransaction();
            $new = Property::toggle('MURO');
            DB::commit();

            return Responser::response(['estado' => $new], Responser::OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return Responser::response(['e' => $e->getMessage()], Responser::SERVER_ERROR);
        }
    }

    public function nopartnersvix(Request $request)
    {
        try {
            DB::beginTransaction();
            $new = Property::toggle('MURO');
            DB::commit();
            return Responser::response(['estado' => $new], Responser::OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return Responser::response(['e' => $e->getMessage()], Responser::SERVER_ERROR);
        }
    }

    public function changeDisablePaymentPos(Request $request)
    {
        try {
            $domain = currentDomain(true);
            $domain->setSetting("disable_payment_pos", $request->value);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function habilitate(Request $request, $id)
    {
        $request->merge(['store_id' => $id]);
        return Stores::habilitate($request);
    }

    public function getReasonsEnable(Request $request)
    {
        $flag = $request['action'] == 0 ? 'enable_store' : 'disable_store';
        return Reason::where('tipo', '=', 'ADMIN_STORES')->where('flag', '=', $flag)->get();
    }

    public function updateShopkeeper(Request $request)
    {
        try {
            $payload = $this->validate($request, Store::updateLojista());
            $loja = Store::with('shopkeeper')->findOrFail($request->id);

            $user = $loja->shopkeeper->user;
            $loja->users()->detach([$user->id]);

            $loja->update($payload);
            $loja->refresh();
            $user = $loja->shopkeeper->user;

            $loja->users()->syncWithoutDetaching([
                    $user->id => [
                        "domain_id" => $loja->domain_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                ]
            );

            $user->status = "ATIVO";
            $user->save();

            return response()->json(true);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getRetention(Request $request)
    {
        $store = Store::withoutGlobalScope(DomainScope::class)->findOrFail($request->loja_id);

        $retentions = $store->wallet->statements()->where("type", "RETENTION")
            ->selectRaw("*, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as date")->orderByDesc('created_at')
            ->limit(15)->get();

        if (!isset($retentions)) {
            $retentions = $store->retencoes()
                ->selectRaw("*, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as date")->orderByDesc('created_at')
                ->limit(15)->get();
        }

        return $retentions;
    }

    public function addRetention(Request $request)
    {
        return Stores::addRetention($request);
    }

    public function removeRetention(Request $request)
    {
        $payload = $this->validate($request, ["estado" => "required|in:CANCELADO"]);

        try {
            $retencao = Retention::findOrFail($request->id);
            $retencao->estado = $payload["estado"];
            $retencao->save();
        } catch (\Exception $e) {
            throw $e;
        }
        return [true];
    }

    public function closeNextDay(Request $request)
    {
        $loja = Store::find($request->loja_id);
        $loja->fechado_ate = Carbon::today()->addDay();
        $loja->save();
        \RedisManager::del("lojas.{$loja->id}.aberto");

        Pusher::sendPrinter("shopkeeper.{$loja->lojista_id}.printer", "store-status", []);
    }

    public function returnOperation(Request $request)
    {
        $loja = Store::find($request->loja_id);
        $loja->fechado_ate = null;
        $loja->setSetting('high_demand_until', null);
        $loja->save();
        \RedisManager::del("lojas.{$loja->id}.aberto");

        Pusher::sendPrinter("shopkeeper.{$loja->lojista_id}.printer", "store-status", []);
    }

    public function getScheduled(Request $request)
    {
        $action = [];
        $loja = Store::find($request->loja_id);
        $v = 0;
        foreach ($loja->schedule_actions as $aa) {
            $action[$v] = $aa;
            $v++;
        }
        return $action;
    }

    public function postScheduled(Request $request)
    {
        ScheduledAction::create([
            'description' => $request->description ?: 'n/d',
            'expected_on' => $request->expected_on,
            'coluna' => $request->coluna,
            'novo_valor' => $request->novo_valor,
            'loja_id' => $request->loja_id
        ]);
        return [true];
    }

    public function deleteScheduled(Request $request)
    {
        $aa = ScheduledAction::find($request->acao_agendada_id);
        $aa->delete();
        return [true];
    }

    public function getProductStore(Request $request)
    {
        $store = Store::find($request->store_id);

        return Store::where('domain_id', $store->domain_id)
        ->like('nome', $request->nome)
        ->orWhere('id', 'like', "%{$request->nome}%")
        ->selectRaw('id, nome')->get();
    }

    public function cloneProduct(Request $request, CloneProduct $cloneProduct)
    {
        $payload = $this->payload($request);
        return response()->json($cloneProduct->execute($payload));
    }

    public function blockExtractStore(Request $request)
    {
        try {
            $loja = Store::findOrFail($request->id);
            $loja->setSetting('extract_block', true);

            $senha = base64_decode($request->confirm);

            $lojista = $loja->getLojista();
            $lojista->senha_extrato = Hash::make($senha);
            $lojista->save();
            return response()->json(["success" => true]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function deleteProduct(Request $request)
    {
        $categorias = Category::where('loja_id', $request->id)->get();

        foreach ($categorias as $categoria) {
            $categoria->produtos()->delete();
        }

        return response()->json(["success" => true]);
    }

    public function resetProductPassword(Request $request)
    {
        try {
            $loja = Store::findOrFail($request->id);
            $lojista = Shopkeeper::find($loja->lojista_id);
            $lojista->senha_produtos = NULL;
            $lojista->save();
            return ['status' => true];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function categories()
    {
        return Category::where('is_primary', 1)->where('ativo', 1)->where('tipo', 'L')
            ->select('id', 'nome as name', 'domain_id')->get();
    }

    public function approveStore($id, ApproveStore $approveStore)
    {
        return $approveStore->execute($id);
    }

    public function reproveStore($id, ReproveStore $reproveStore)
    {
        return $reproveStore->execute($id);
    }

    public function authShopkeeper(Request $request, $storeId)
    {
        $store = Store::with('domain')->findOrFail($storeId);
        $token = str_random(8) . md5(uniqid(Auth::id(), true));
        $store->setSetting("shopkeeper_access_token", json_encode(['user_id' => Auth::id(), 'token' => $token]));
        $url = $store->domain->getSetting("shopkeeper_platform_url");
        if (env('APP_ENV', 'dev') == 'staging') {
            $url = 'https://adelivery.staging.stores.packk.com.br';
        }
        return ['url' => "{$url}/auth/login/$token"];
    }

    public function destroy(Request $request, $id, DeleteUser $deleteUser)
    {
        try {
            DB::beginTransaction();
            $store = Store::with('shopkeeper')->find($id);
            $store->ativo = 0;
            $store->save();
            DB::table('categoria_loja')->where('loja_id', $store->id)->delete();

            if(!Store::where('lojista_id', $store->lojista_id)->where('id', '<>', $store->id)->exists()){
                $user = $store->shopkeeper->user;
                $deleteUser->execute($user);
                event(new UserLoggedOut($user));
            }

            $store->delete();

            DB::commit();
            dispatch(new SendShopFeedEvent('store.destroy', $store->id));
            return ['status' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function externalCreate(Request $request, ExternalCreateStore $createStore)
    {
        $this->validate($request, ExternalCreateStore::validateRules(), ExternalCreateStore::validateMessages());
        return response()->json($createStore->execute($this->payload($request)));
    }

    public function history(Request $request, $storeId)
    {
        return StoreActivity::with(['user', 'reason'])
            ->where('store_id', $storeId)
            ->orderByDesc('created_at')->get();
    }

    public function banDeliveryman(Request $request, $id, BanDeliveryman $banDeliveryman)
    {
        $payload = $this->validate($request, [
            'deliveryman_id' => 'required',
        ]);

        try {
            $banDeliveryman->execute($id, $payload['deliveryman_id']);
            return ApiResponse::sendResponse();
        } catch (CustomException $e) {
            return ApiResponse::sendError($e->getMessage());
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Registro não encontrado');
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function unbanDeliveryman(Request $request, $id, UnbanDeliveryman $unbanDeliveryman)
    {
        $payload = $this->validate($request, [
            'deliveryman_id' => 'required',
        ]);

        try {
            $unbanDeliveryman->execute($id, $payload['deliveryman_id']);
            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Registro não encontrado');
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function getBanDeliveryman($id)
    {
        return Ban::withoutGlobalScope(DomainScope::class)
            ->join('entregadores', 'entregadores.id', '=', 'banimentos.entregador_id')
            ->join('users', 'users.id', '=', 'entregadores.user_id')
            ->where('banimentos.loja_id', $id)
            ->selectRaw("entregadores.id, CONCAT(users.nome, ' ', users.sobrenome) as name")
            ->get();

    }

    public function prepareBanDeliveryman(Request $request)
    {
        return Deliveryman::query()
            ->join('users', 'entregadores.user_id', 'users.id')
            ->where(function($q) use($request) {
                $q->where('entregadores.id', $request->nome)->orWhere('users.nome', 'like', "%{$request->nome}%");
            })->selectRaw("entregadores.id, CONCAT(entregadores.id, ' - ', users.nome, ' ', users.sobrenome) as name")
            ->simplePaginate(10);
    }
}
