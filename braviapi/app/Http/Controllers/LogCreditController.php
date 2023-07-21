<?php

namespace App\Http\Controllers;

use App\Excel\Imports\LogCreditsImport;
use App\Exceptions\GenericException;
use App\Http\Requests\LogCredit\InsertMultipleCreditRequest;
use App\Response\ApiResponse;
use App\Rules\Customer\InsertLogCredit;
use App\Rules\Customer\InsertMultipleLogCredit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Customer;
use Packk\Core\Models\LogCredit;
use Packk\Core\Models\LogTable;

class LogCreditController extends Controller
{
    public function index(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        return [
            'data' => LogCredit::where('cliente_id', $customer->id)->orderByDesc('created_at')->simplePaginate(15),
            'total' => $customer->get_credits()
        ];
    }

    public function store(Request $request, $customerId, InsertLogCredit $insertLogCredit)
    {
        $payload = array_merge($request->all(), ['customer_id' => $customerId]);
        $insertLogCredit->execute($payload);
        return ['success' => true];
    }

    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, ["expired_at" => "required"]);
        $log = LogCredit::find($id);

        LogTable::log('UPDATE', "log_creditos", $log->id, 'expira_at', $log->expira_at, $payload['expired_at']);
        $log->expira_at = $payload['expired_at'];
        $log->save();

        return ['success' => true, 'data' => $log];
    }

    public function storeMultiple(InsertMultipleCreditRequest $request, InsertMultipleLogCredit $logCredit)
    {
        $payload = $request->validated();
        try {
            if ($request->hasFile('file')) {
                $import = new LogCreditsImport;
                \Excel::import($import, $request->file);
                $payload['customers'] = $import->getRows();
            }

            $data = $logCredit->execute($payload);
            return ApiResponse::sendResponse($data);
        } catch (GenericException $ex) {
            return ApiResponse::sendError($ex->getMessage());
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function history(Request $request)
    {
        $logs = LogTable::where('table', 'log_credits:multiple')
            ->orderByDesc('created_at')->limit(20)
            ->get()->pluck('after_value');
        foreach ($logs as $key => $log) {
            $logs[$key] = json_decode($log);
        }

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
