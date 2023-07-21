<?php

namespace App\Rules\Sales;

use Packk\Core\Models\Order;

class ListSales
{
    public function execute(array $payload)
    {
        $query = Order::with(['store', 'customer.user']);

        if (!empty($payload['search'])) {
            if ($payload['type'] == 'id') {
                $query->where('id', $payload['search']);
            }

            if ($payload['type'] == 'loja') {
                $query->whereHas('store', function ($q) use ($payload) {
                    $q->where("nome", 'LIKE', "%{$payload['search']}%")->orWhere('id', $payload['search']);
                });
            }

            if ($payload['type'] == 'email') {
                $query->whereHas('customer.user', function ($q) use ($payload) {
                    $q->where("email", $payload['search']);
                });
            }
        }

        $data = $query->orderByDesc('id')->simplePaginate(10);

        $response = $data->toArray();
        foreach ($data->items() as $key => $order) {
            $response['data'][$key] = [
                'id' => $order->id,
                'domain_id' => $order->domain_id,
                'status' => $order->estado,
                'status_text' => Order::parseStatus($order->estado),
                'created_at' => $order->created_at,
                'store' => $order->store->nome,
                'customer' => isset($order->customer->user) ? "{$order->customer->user->nome} {$order->customer->user->sobrenome}" : "",
            ];
        }
        return $response;
    }
}