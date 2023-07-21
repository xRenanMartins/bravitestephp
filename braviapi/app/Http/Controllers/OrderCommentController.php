<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use App\Rules\Sales\SaveComment;
use App\Rules\Sales\UpdateComment;
use Illuminate\Http\Request;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\PedidoComments;

class OrderCommentController extends Controller
{
    public function store(Request $request, $orderId, SaveComment $saveComment)
    {
        $payload = $request->validate(['comment' => 'required']);
        return $saveComment->execute($orderId, $payload);
    }

    public function update(Request $request, $orderId, $id, UpdateComment $updateComment)
    {
        $payload = $request->validate(['comment' => 'required']);
        $response = $updateComment->execute($id, $payload['comment']);
        return response()->json($response);
    }

    public function destroy(Request $request, $orderId, $id)
    {
        PedidoComments::destroy($id);
        LogTable::log('DELETE', "pedido_comments", $id);
        return ApiResponse::sendResponse();
    }
}
