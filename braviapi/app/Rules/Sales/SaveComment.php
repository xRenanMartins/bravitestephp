<?php

namespace App\Rules\Sales;

use Illuminate\Support\Facades\Auth;
use Packk\Core\Models\Order;

class SaveComment
{
    public function execute($orderId, $payload)
    {
        try {
            $order = Order::findOrFail($orderId);

            $payload['domain_id'] = $order->domain_id;
            $payload['user_id'] = Auth::id();

            $newItem = $order->comments()->create($payload);

            $newItem = $newItem->toArray();
            $newItem['user'] = Auth::user()->full_name;
            return [
                'success' => true,
                'item' => $newItem
            ];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Não foi possível salvar o seu comentário",
                'error' => $e->getMessage()
            ], 400);
        }
    }
}