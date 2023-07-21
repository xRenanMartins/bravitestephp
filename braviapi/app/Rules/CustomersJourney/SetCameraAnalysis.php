<?php

namespace App\Rules\CustomersJourney;

use Packk\Core\Models\CustomerJourney;
use Packk\Core\Models\Product;
use Packk\Core\Models\Order;
use Packk\Core\Models\StolenStore;

class SetCameraAnalysis
{
    private $payload;
    private $journey;
    private $userId;
    private $isFirstCameraAnalysis = false;

    public function execute($id, $payload, $userId)
    {
        $this->payload = $payload;
        $this->userId = $userId;
        $this->journey = CustomerJourney::query()->find($id);
        $this->isFirstCameraAnalysis = empty($this->journey->camera_analysis);

        $this->saveActivity();
        $this->saveProductsInStolenStore();

        $this->journey->camera_analysis = $payload->camera_analysis;
        $this->journey->camera_analysis_status = $payload->camera_analysis_status;
        $this->journey->description = $payload->description;
        $this->journey->save();

        $status = $this->getNewStatus();
        $result = $this->journey->toArray();
        $result['status'] = $status;

        return $result;
    }

    private function saveProductsInStolenStore()
    {
        if (isset($this->payload->products)) {
            foreach ($this->payload->products as $product) {
                $stolenStore = new StolenStore();
                $stolenStore->customer_journey_id = $this->journey->id;
                $stolenStore->quantity = $product->quantity;
                $stolenStore->payed = $product->payed;
                $stolenStore->payment_method = $product->payment_method;

                $productLocal = Product::find($product->id);
                $stolenStore->product_id = $productLocal->id;
                $stolenStore->name = $productLocal->nome;
                $stolenStore->value = $productLocal->preco;

                $stolenStore->save();
            }
        }
    }

    private function saveActivity()
    {
        $json = [
            "journey_id" => $this->journey->id,
            "camera_analysis" => $this->payload->camera_analysis,
            "primeiro_registro" => $this->isFirstCameraAnalysis
        ];
        $this->journey->customer->user->addAtividade("JOURNEY_ANALYSIS", ["[::json]" => json_encode($json)], $this->userId, "ADMIN");
    }

    private function getNewStatus()
    {
        $orders = Order::query()
            ->select(['pedidos.id', 'pedidos.estado_pagamento as status'])
            ->join('customers_journey', 'customers_journey.order_id', '=', 'pedidos.id')
            ->where('customers_journey.parent_id', $this->journey->id)
            ->get()->toArray();

        $orders = array_sort($orders, 'id', SORT_DESC);
        return ListCustomersJourney::defineStatus($this->journey, $orders);
    }
}