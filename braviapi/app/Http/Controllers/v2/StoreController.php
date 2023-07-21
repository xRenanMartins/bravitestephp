<?php

namespace App\Http\Controllers\v2;

use App\Exceptions\GenericException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreRegistrationDataRequest;
use App\Http\Requests\Store\UpdateRegistrationDataRequest;
use App\Response\ApiResponse;
use App\Rules\Store\V2\DataEditStore;
use App\Rules\Store\V2\GetSettingsStore;
use App\Rules\Store\V2\GetStorePaymentMethods;
use App\Rules\Store\V2\SaveStoreRegistrationData;
use App\Rules\Store\V2\UpdateSettingsStore;
use App\Rules\Store\V2\UpdateStorePaymentMethods;
use App\Rules\Store\V2\UpdateStoreRegistrationData;
use App\Rules\Store\V2\UpdateStoreShopkeeper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Jobs\Admin\SendStoreLegiti;
use Packk\Core\Jobs\Admin\SyncMarketProductCategories;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Store;

class StoreController extends Controller
{
    public function create(Request $request, DataEditStore $dataEditStore)
    {
        try {
            return ApiResponse::sendResponse($dataEditStore->execute());
        } catch (ModelNotFoundException $ex) {
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function edit(Request $request, $id, DataEditStore $dataEditStore)
    {
        try {
            return ApiResponse::sendResponse($dataEditStore->execute($id));
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (CustomException $e) {
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function store(StoreRegistrationDataRequest $request, SaveStoreRegistrationData $registrationData)
    {
        $payload = $request->validated();
        try {
            DB::beginTransaction();
            $store = $registrationData->execute($payload);
            DB::commit();

            dispatch(new SendShopFeedEvent($store->id, 'store.create'));

            if ($store->isMarket()) {
                dispatch(new SyncMarketProductCategories($store->id));
            }

            return ApiResponse::sendResponse(["store_id" => $store->id]);
        } catch (GenericException $ex) {
            DB::rollBack();
            return ApiResponse::sendError($ex->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function update(UpdateRegistrationDataRequest $request, $id, UpdateStoreRegistrationData $registrationData)
    {
        $payload = $request->validated();
        try {
            DB::beginTransaction();
            $registrationData->execute($id, $payload);
            DB::commit();

            $changed = $registrationData->getChanges();
            dispatch(new SendShopFeedEvent($id, 'store.update', $changed));
            if (in_array('category', $changed)) {
                dispatch(new SendShopFeedEvent($id, 'category.update'));
            }
            if (in_array('service_area', $changed)) {
                dispatch(new SendShopFeedEvent($id, 'service_area:update'));
            }

            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            DB::rollBack();
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (GenericException $ex) {
            DB::rollBack();
            return ApiResponse::sendError($ex->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function settings(Request $request, $id, GetSettingsStore $getSettingsStore)
    {
        try {
            return ApiResponse::sendResponse($getSettingsStore->execute($id));
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function updateSettings(Request $request, $id, UpdateSettingsStore $settingsStore)
    {
        try {
            DB::beginTransaction();
            $settingsStore->execute($request->all(), $id);
            DB::commit();
            dispatch(new SendShopFeedEvent($id, 'settings:update'));
            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            DB::rollBack();
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function payments(Request $request, $id, GetStorePaymentMethods $getStorePaymentMethods)
    {
        try {
            return ApiResponse::sendResponse($getStorePaymentMethods->execute($id));
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Loja não encontrada!');
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function updatePayments(Request $request, $id, UpdateStorePaymentMethods $storePaymentMethods)
    {
        try {
            DB::beginTransaction();
            $storePaymentMethods->execute($id, $request->all());
            DB::commit();
            return ApiResponse::sendResponse();
        } catch (GenericException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function updateShopkeeper(Request $request, UpdateStoreShopkeeper $updateStoreShopkeeper)
    {
        try {
            DB::beginTransaction();
            $updateStoreShopkeeper->execute($request->all());

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