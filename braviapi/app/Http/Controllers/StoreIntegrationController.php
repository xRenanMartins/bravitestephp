<?php

namespace App\Http\Controllers;

use App\Integration\IntegrationApi\Services\Application;
use App\Integration\IntegrationApi\Services\Store;
use App\Response\ApiResponse;
use Illuminate\Http\Request;
use Packk\Core\Models\Store as StoreModel;

class StoreIntegrationController extends Controller
{
    public function index(Request $request, $storeId, Store $storeService)
    {
        $store = StoreModel::findOrFail($storeId);

        $data = $storeService->getStoreIntegrations($store);
        return ApiResponse::sendResponse($data);
    }

    public function appsAvailable(Request $request, $storeId, Application $application)
    {
        $store = StoreModel::findOrFail($storeId);

        $data = $application->listApps($store->domain_id);

        $arrayData = [];

        foreach ($data as $item) {
            if ($item->allow_all_stores || in_array($store->id, $item->stores)) {
                if (in_array("BR", $item->places) || in_array($store->address->state, $item->places)) {
                    $arrayData[] = $item;
                }
            }
        }

        return ApiResponse::sendResponse($arrayData);
    }

    public function AddStoreIntegration(Request $request, $storeId, Store $storeService)
    {
        $payload = $this->validate($request, ["integration" => "required", "version" => "required"]);

        $store = StoreModel::findOrFail($storeId);

        $data = $storeService->createStoreIntegration($store, $payload['integration'], $payload['version']);
        return ApiResponse::sendResponse($data);
    }

    public function UpdateStoreIntegration(Request $request, $storeId, Store $storeService)
    {
        $payload = $request->all();

        $store = StoreModel::findOrFail($storeId);

        $data = $storeService->editStoreIntegration($store, $payload['integration'], $payload);
        return ApiResponse::sendResponse($data);
    }

    public function ChangeStoreIntegration(Request $request, $storeId, Store $storeService)
    {
        $payload = $this->validate($request, ["integration" => "required", "action" => "required"]);

        $store = StoreModel::findOrFail($storeId);

        $data = $storeService->changeStoreIntegration($store, $payload['integration'], $payload['action']);
        return ApiResponse::sendResponse($data);
    }

    public function deleteStoreIntegration(Request $request, $storeId, $integration, Store $storeService)
    {
        $store = StoreModel::findOrFail($storeId);

        $data = $storeService->deleteStoreIntegration($store, $integration, $request->version);
        return ApiResponse::sendResponse($data);
    }
}
