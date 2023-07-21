<?php

namespace App\Rules\Sales;

use Packk\Core\Models\Customer;
use Packk\Core\Models\Order;
use Packk\Core\Models\User;

class ShowSale
{
    public function execute($id)
    {
        $order = Order::with(['store', 'customer.user', 'payment_method', 'addressConcierge', 'voucher', 'delivery.entregador'])->findOrFail($id);
        $address = $order->addressConcierge ?? $order->customerAddress;

        $excluded = '';
        $customer = $order->customer;
        if (empty($customer)) {
            $customer = Customer::withTrashed()->find($order->cliente_id);
            $excluded = '(excluído)';
        }

        $user = $customer->user;
        if (empty($user)) {
            $user = User::withTrashed()->find($customer->user_id);
        }

        return [
            'order_id' => $order->id,
            'store' => [
                'id' => $order->loja_id,
                'name' => $order->store->nome,
                'phone' => preg_replace('/\D/', '', $order->store->telefone)
            ],
            'customer' => [
                'name' => "{$user->nome} {$user->sobrenome} {$excluded}",
                'email' => $user->email,
                'phone' => preg_replace('/\D/', '', $user->telefone),
                'total_orders' => $customer->orders()->where('estado', 'F')->count()
            ],
            'order' => [
                'payment_method' => $order->payment_method->name,
                'payment_mode' => $order->payment_method->mode,
                'address' => "{$address->cidade} - {$address->state}",
                'delivery_method' => $order->modo_entrega,
                'estado_pagamento' => $order->estado_pagamento,
                'voucher' => $order->voucher->chave ?? null,
            ],
            'values' => [
                'total' => $order->amount,
                'total_products' => $order->valor,
                'delivery_tax' => $order->taxa_entrega_cliente,
                'credits' => $order->creditos_payout,
                'voucher' => $order->voucher_payout
            ],
            'deliveryman' => isset($order->delivery->deliveryman) ? [
                'name' => $order->delivery->deliveryman->user->nome,
                'phone' => $order->delivery->deliveryman->user->telefone,
            ] : null,
        ];
    }
}