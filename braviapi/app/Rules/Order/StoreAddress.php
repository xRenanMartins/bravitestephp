<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-07-29
 * Time: 10:29
 */

namespace App\Rules\Order;

use Packk\Core\Models\Order;
use Packk\Core\Models\Address;
use Packk\Core\Models\DeliveryPoint;
use App\Http\Controllers\Responser;

class StoreAddress
{
    public function execute($payload)
    {
        try {
            $pedido = Order::findOrFail($payload->pedido_id);
            if (($pedido->estado == 'T') && ($pedido->estado == 'F')) {
                return Responser::response([], Responser::FORBIDEN_ERROR);
            } else {
                $address = new Address();
                $address->endereco = $payload->cliente_logradouro . '';
                $address->numero = $payload->cliente_numero . '';
                $address->bairro = $payload->cliente_bairro . '';
                $address->cidade = $payload->cliente_cidade . '';
                $address->cep = $payload->cliente_cep . '';
                $address->complemento = $payload->cliente_complemento . '';
                $address->latitude = $payload->cliente_latitude . '';
                $address->longitude = $payload->cliente_longitude . '';
                $address->save();
                $deliveryPoint = new DeliveryPoint();
                $pedido->endereco_cliente_id = $address->id;
                $deliveryPoint->cliente()->associate($pedido->cliente);
                $deliveryPoint->endereco()->associate($address);
                $deliveryPoint->label = $payload->cliente_logradouro;
                $deliveryPoint->save();
                $pedido->save();
                if ($pedido->entrega) {
                    if (isset($pedido->entrega->entregador)) {
                        $pedido->entrega->entregador->push_reload();
                    }
                }
                return Responser::response([], Responser::OK);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}