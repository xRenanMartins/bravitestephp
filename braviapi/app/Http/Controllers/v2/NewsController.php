<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\News\StoreNewsRequest;
use App\Http\Requests\News\UpdateNewsRequest;
use App\Response\ApiResponse;
use App\Rules\News\CreateNews;
use App\Rules\News\DataNewsEdit;
use App\Rules\News\ExpireNews;
use App\Rules\News\UpdateNews;
use App\Rules\News\UpdateNewsStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Exceptions\CustomException;

class NewsController extends Controller
{
    public function create(Request $request, DataNewsEdit $dataNewsEdit)
    {
        try {
            return ApiResponse::sendResponse($dataNewsEdit->execute());
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function edit(Request $request, $id, DataNewsEdit $dataNewsEdit)
    {
        try {
            return ApiResponse::sendResponse($dataNewsEdit->execute($id));
        } catch (ModelNotFoundException $ex) {
            return ApiResponse::sendError('Novidade não encontrada');
        } catch (\Exception $ex) {
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function store(StoreNewsRequest $request, CreateNews $createNews)
    {
        $payload = $request->validated();
        try {
            DB::beginTransaction();
            $voucher = $createNews->execute($payload);
            DB::commit();
            return ApiResponse::sendResponse(['voucher_id' => $voucher->id], 201);
        } catch (CustomException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function update(UpdateNewsRequest $request, $id, UpdateNews $updateNews)
    {
        $payload = $request->validated();
        try {
            DB::beginTransaction();
            $updateNews->execute($id, $payload);
            DB::commit();
            return ApiResponse::sendResponse();
        } catch (CustomException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (ModelNotFoundException) {
            DB::rollBack();
            return ApiResponse::sendError('Novidade não encontrada');
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function updateStatus(Request $request, $id, UpdateNewsStatus $updateNews)
    {
        try {
            DB::beginTransaction();
            $updateNews->execute($id, $request->get('paused', false));
            DB::commit();
            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            DB::rollBack();
            return ApiResponse::sendError('Novidade não encontrada');
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }

    public function expire($id, ExpireNews $expireNews)
    {
        try {
            DB::beginTransaction();
            $expireNews->execute($id);
            DB::commit();
            return ApiResponse::sendResponse();
        } catch (ModelNotFoundException) {
            DB::rollBack();
            return ApiResponse::sendError('Novidade não encontrada');
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }
}