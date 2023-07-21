<?php

namespace App\Http\Controllers;

use App\Exceptions\GenericException;
use App\Response\ApiResponse;
use App\Rules\Sales\ListSales;
use App\Rules\Sales\ShowSale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Actions\Admin\Address\UpdateAddress;
use Packk\Core\Actions\Admin\Order\CaptureOrderTransaction;
use Packk\Core\Actions\Admin\Order\GetPaymentData;
use Packk\Core\Actions\Admin\Order\GetProducts;
use Packk\Core\Actions\Admin\Order\ListActivities;
use Packk\Core\Actions\Admin\Order\ReverseOrderTransaction;
use Packk\Core\Actions\Admin\Order\RollbackOrderStatus;
use Packk\Core\Actions\Admin\Order\UpdateOrderStatus;
use Packk\Core\Actions\Admin\Reasons\CancelReasons;
use Packk\Core\Actions\Customer\Orders\NotificationStore;
use Packk\Core\Actions\Stores\CancelOrder;
use Packk\Core\Actions\Stores\UndoOrder;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\Order;
use Packk\Core\Scopes\DomainScope;

class SalesController extends Controller
{
    public function index(Request $request, ListSales $sales)
    {
        return $sales->execute($request->all());
    }

    public function show(Request $request, $id, ShowSale $showSale)
    {
        try {
            return $showSale->execute($id);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::sendError('Pedido não encontrado!');
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function products(Request $request, $id, GetProducts $getProducts)
    {
        return $getProducts->execute($id);
    }

    public function activities(Request $request, $id, ListActivities $activities)
    {
        return $activities->execute($id);
    }

    public function getSituation(Request $request, $orderId)
    {
        $order = Order::with('delivery')->find($orderId);

        switch ($order->estado) {
            case 'R':
                $codeStatus = empty($order->delivery->entregador_id) && $order->modo_entrega != 'AMEFLASH' ? 1 : 0;
                break;
            case 'A':
                $codeStatus = 2;
                break;
            case 'T':
                $deliveryStatus = $order->delivery->estado ?? '';
                $codeStatus = match ($deliveryStatus) {
                    'P' => 4,
                    'I' => 3,
                    default => 0,
                };
                break;
            case 'F':
                $codeStatus = 5;
                break;
            default:
                $codeStatus = 0;
        }

        return ApiResponse::sendResponse([
            'code' => $codeStatus,
            'type' => $order->tipo,
            'payment_method' => $order->metodo_pagamento,
            'delivery_id' => $order->delivery->id,
        ]);
    }

    public function rollbackSituation(Request $request, $orderId, UpdateOrderStatus $orderStatus)
    {
        $payload = $this->payload($request);
        $payload['pedido_id'] = $orderId;

        return $orderStatus->execute($payload);
    }

    public function updateSituation(Request $request, $orderId, UpdateOrderStatus $orderStatus, RollbackOrderStatus $rollbackOrderStatus)
    {
        try {
            DB::beginTransaction();
            $payload = $request->all();
            if ($request->action === 'rollback') {
                $response = $rollbackOrderStatus->execute($orderId, $payload);
            } else {
                $payload['pedido_id'] = $orderId;
                $response = $orderStatus->execute($payload);
            }
            DB::commit();

            return ApiResponse::sendResponse($response);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function getCustomerAddress(Request $request, $orderId)
    {
        $order = Order::with('customerAddress')->find($orderId);
        if (empty($order->customerAddress)) {
            throw new GenericException('Endereço do cliente não encontrado');
        }

        return ApiResponse::sendResponse($order->customerAddress);
    }

    public function updateAddress(Request $request, $orderId, UpdateAddress $updateAddress)
    {
        $payload = $this->payload($request);
        $payload->pedido_id = $orderId;

        return $updateAddress->execute($payload);
    }

    public function getPaymentHistory(Request $request, $orderId, GetPaymentData $getPaymentHistory)
    {
        $result = $getPaymentHistory->execute($orderId);
        if (!$result['success']) {
            throw new GenericException($result['message']);
        }

        unset($result['success']);
        return ApiResponse::sendResponse($result);
    }

    public function reasonsToCancel(Request $request, $orderId, CancelReasons $cancelReasons)
    {
        $data = $cancelReasons->execute($orderId);
        return ApiResponse::sendResponse($data);
    }

    public function cancelOrder(Request $request, $orderId, CancelOrder $cancelOrder)
    {
        $payload = [
            "user_id" => Auth::id(),
            "reason_id" => $request->motivo_id,
            "reason_description" => $request->motivo_recusa_admin,
            "canceled_by" => $request->cancelado_por,
            "notify_shoppkeeper_cancel_order" => $request->notify_shoppkeeper_cancel_order,
        ];
        return $cancelOrder->execute($payload, $orderId);
    }

    public function undoOrder(Request $request, $orderId, UndoOrder $undoOrde)
    {
        try {
            $payload = [
                "order_id" => $orderId,
            ];
            $res = $undoOrde->execute($payload);
            return ApiResponse::sendResponse($res);
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function reversalTransaction(Request $request, $orderId, ReverseOrderTransaction $reversalTransaction)
    {
        try {
            $reversalTransaction->execute($orderId);
            return ApiResponse::sendResponse();
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function captureTransaction(Request $request, $orderId, CaptureOrderTransaction $captureTransaction)
    {
        try {
            $result = $captureTransaction->execute($orderId);
            if ($result['captured']) {
                return ApiResponse::sendResponse($result);
            } else {
                return ApiResponse::sendError('Nao foi possível realizar a captura da transação desse pedido', 400, $result);
            }
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function sendToShopkeeper(Request $request, $id)
    {
        try {
            $order = Order::withoutGlobalScope(DomainScope::class)->findOrFail($id);
            $order->add_atividade('ADMIN_SEND_TO_SHOPPKEEPER',  ['[::user]' => Auth::user()->nome]);
        } catch (\Exception $e) {
            return Responser::response([],Responser::NOT_FOUND_ERROR);
        }

        if(!$order->store->esta_aberto()) {
            throw new RuleException("Ops...", "Esta loja está fechada no momento, não é possível enviar pedido para ela.", 430);
        }

        try {
            if($order->estado == 'P') {
                $order->updateState('R');

                NotificationStore::send($order, true);
            }
            return Responser::response([],Responser::OK);
        } catch (\Exception $e) {
            return Responser::response(['valor' => $e->getMessage()],Responser::FORBIDEN_ERROR);
        }
    }
}
