<?php

namespace App\Http\Controllers;

use App\Excel\Imports\DeliveryTaxImport;
use App\Exceptions\GenericException;
use App\Http\Requests\DeliveryTax\InsertMultipleDeliveryTaxRequest;
use App\Response\ApiResponse;
use App\Rules\Setting\InsertMultipleDeliveryTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\LogTable;

class DeliveryTaxController extends Controller
{
    public function storeMultiple(InsertMultipleDeliveryTaxRequest $request, InsertMultipleDeliveryTax $deliveryTax)
    {
        $payload = $request->validated();
        try {
            if ($request->hasFile('file')) {
                $import = new DeliveryTaxImport;
                \Excel::import($import, $request->file);
                $payload['ids'] = $import->getRows();
            }

            $data = $deliveryTax->execute($payload);
            return ApiResponse::sendResponse($data);
        } catch (GenericException $ex) {
            return ApiResponse::sendError($ex->getMessage());
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function history(Request $request)
    {
        $logs = LogTable::where('table', 'delivery_tax:multiple')
            ->orderByDesc('created_at')->limit(20)
            ->get()->pluck('after_value');
        foreach ($logs as $key => $log) {
            $logs[$key] = json_decode($log);
        }

        return ApiResponse::sendResponse($logs);
    }
}
