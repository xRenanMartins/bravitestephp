<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Response\ApiResponse;
use App\Rules\Voucher\v2\CreateVoucher;
use App\Rules\Voucher\v2\ShowVoucher;
use App\Rules\Voucher\v2\UpdateVoucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Domain;
use Packk\Core\Models\FirebaseTopic;

class VoucherController extends Controller
{
    public function store(Request $request, CreateVoucher $voucher)
    {
        $payload = $this->payload($request);
        try {
            $result = $voucher->execute($payload);
            return ApiResponse::sendResponse($result);
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function update(Request $request, $id, UpdateVoucher $voucher)
    {
        try {
            $payload = $this->payload($request);
            $result = $voucher->execute($payload, $id);

            return ApiResponse::sendResponse($result);
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Registro não encontrado!');
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function show(Request $request, $id, ShowVoucher $voucher)
    {
        try {
            $result = $voucher->execute($id);
            return ApiResponse::sendResponse($result);
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Registro não encontrado!');
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function showList(Request $request, $id, $action)
    {
        if ($action == 'stores') {
            $query = DB::table('loja_voucher');
            $idField = 'loja_id';
        } else {
            $query = DB::table('voucher_customer');
            $idField = 'cliente_id';
        }

        $query->where('voucher_id', $id);
        if (!empty($request->name)) {
            $query->where($idField, $request->name);
        }

        $result = $query->selectRaw($idField . ' as id')->get()->pluck('id');
        return ApiResponse::sendResponse($result);
    }

    public function create(Request $request)
    {
        $franchises = FirebaseTopic::query()
            ->join('franchises', 'franchises.firebase_topic_id', 'firebase_topics.id')
            ->where('franchises.active', 1)->pluck('firebase_topics.id');

        $regions = FirebaseTopic::query()
            ->where('type', 'CLIENTE')->orWhere(function ($q) use ($franchises) {
                $q->where('type', 'FRANQUIA')->whereIn('firebase_topics.id', $franchises);
            })->selectRaw("IF(type = 'FRANQUIA', CONCAT(firebase_topics.type, ' ', firebase_topics.name), firebase_topics.name) as name")
            ->selectRaw('firebase_topics.id')->orderBy('type')->orderBy('id')->get();

        return ApiResponse::sendResponse([
            'regions' => $regions,
            'domains' => Domain::get()->toArray()
        ]);
    }
}