<?php

namespace App\Rules\Voucher\v2;

use App\Jobs\Vouchers\LinkVoucherClients;
use App\Jobs\Vouchers\LinkVoucherProducts;
use App\Jobs\Vouchers\LinkVoucherStore;
use App\Traits\DateTrait;
use Illuminate\Support\Str;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Store;
use Packk\Core\Models\Voucher;

class UpdateVoucher
{
    use DateTrait;
    private $payload;

    public function execute($payload, $id)
    {
        if (in_array($payload->tipo_desconto, ['A', 'FRETE_FIXO', 'VALOR_MAXIMO', 'VISUAL', 'FRETE_GRATIS'])) {
            $desconto = $payload->desconto * 100;
        } else if (in_array($payload->tipo_desconto, ['P', 'FRETE_PERCENTUAL'])) {
            $desconto = $payload->desconto;
        } else {
            $cash_back = $payload->desconto;
            $desconto = 0;
        }

        $regiao = isset($payload->regioes) && count($payload->regioes) > 0 ? 'LISTA' : null;

        $voucher = Voucher::withoutGlobalScope('App\Scopes\DomainScope')->findOrFail($id);
        $voucher->inicio = isset($payload->inicio) ? self::getFormattedDate($payload->inicio) : null;
        $voucher->validade = isset($payload->validade) ? self::getFormattedDate($payload->validade) : null;
        $voucher->valor_minimo = $payload->valor_minimo * 100;
        $voucher->quantidade_total = $payload->quantidade_total;
        $voucher->quantidade_por_usuario = $payload->quantidade_por_usuario;
        $voucher->primeira_compra = isset($payload->primeira_compra) && $payload->primeira_compra !== "N";
        $voucher->broadcast = $payload->veiculacao;
        $voucher->desconto = $desconto;
        $voucher->cash_back = $cash_back ?? 0;
        $voucher->tipo_desconto = $payload->tipo_desconto;
        $voucher->chave = $payload->chave;
        $voucher->limite_valor = $payload->limite_valor;
        $voucher->frete_gratis = $payload->tipo_desconto == "FRETE_GRATIS";
        $voucher->tipo_loja = $payload->tipo_loja ?? "TODAS";
        $voucher->description = $payload->description;
        $voucher->observation = $payload->observation;
        $voucher->recurrence_promotion = $payload->recurrence_promotion;
        $voucher->regiao = $regiao;
        $voucher->payment_type = in_array($payload->payment_type, ['ONLINE', 'OFFLINE']) ? Str::upper($payload->payment_type) : null;
        $voucher->time = $payload->time;
        $voucher->delivery_method = $payload->delivery_method;
        $voucher->employee = $payload->employee;
        $voucher->channels = $payload->channels ?? "TODAS";
        $voucher->device = $payload->device ?? "TODAS";

        $voucher->categorias = $payload->categorias;
        if ($payload->tipo_desconto == 'CB') {
            $voucher->cashback_validity = $payload->cashback_validity;
        }

        $voucher->save();

        // Lojas
        if ($payload->selectStores == 'list') {
            $newStores = $payload->new_stores ?? [];
            $removeStores = $payload->remove_stores ?? [];

            if (count($newStores) > 0) {
                $storesId = Store::select('id')->whereIn('id', array_unique($newStores))->get()->pluck('id')->toArray();
                $storesId = array_diff($storesId, $removeStores);
                $storesIdArray = array_chunk($storesId, 2000);

                if (count($storesIdArray) > 0) {
                    foreach ($storesIdArray as $listIds) {
                        dispatch(new LinkVoucherStore([
                            'new_stores' => $listIds,
                            'remove_stores' => '',
                            'selectStores' => $payload->selectStores,
                            'store_blacklist' => $payload->store_blacklist ?? false
                        ], $voucher, $id));
                    }
                }
            }

            if (count($removeStores) > 0) {
                $storesIdRemoveArray = array_chunk($removeStores, 2000);
                foreach ($storesIdRemoveArray as $listIdsRemove) {
                    dispatch(new LinkVoucherStore([
                        'new_stores' => '',
                        'remove_stores' => $listIdsRemove,
                        'selectStores' => $payload->selectStores,
                        'store_blacklist' => $payload->store_blacklist ?? false
                    ], $voucher, $id));
                }
            }
        } else {
            if (isset($payload->typeIDStores) && in_array($payload->typeIDStores, ['adicionar', 'substituir'])) {
                $array = $payload->new_stores ?? [];
                if (isset($payload->groupIDStores)) {
                    $array = $payload->groupIDStores;
                }

                if (count($array) > 0) {
                    if ($payload->typeIDStores == "substituir") {
                        Voucher::find($id)->stores()->detach();
                    }
                    $array = Store::select('id')->whereIn('id', array_unique($array))->get()->pluck('id')->toArray();

                    $storesIdsArray = array_chunk($array, 2000);
                    foreach ($storesIdsArray as $listStoreIds) {
                        dispatch(new LinkVoucherStore([
                            'groupIDStores' => $listStoreIds,
                            'typeIDStores' => $payload->typeIDStores,
                            'selectStores' => $payload->selectStores,
                            'store_blacklist' => $payload->store_blacklist ?? false
                        ], $voucher, $id));
                    }
                }
            }
        }
        // end lojas

        // Clientes
        if ($payload->selectCustomers == 'list') {
            $newCustomers = $payload->new_customers ?? [];
            $removeCustomers = $payload->remove_customers ?? [];

            if (count($newCustomers) > 0) {
                $customersIds = Customer::select('id')->whereIn('id', array_unique($newCustomers))->get()->pluck('id')->toArray();
                $customersIds = array_diff($customersIds, $removeCustomers);
                $customersIdsArray = array_chunk($customersIds, 2000);
                foreach ($customersIdsArray as $listCustomerIds) {
                    dispatch(new LinkVoucherClients([
                        'new_customers' => $listCustomerIds,
                        'remove_customers' => '',
                        'selectCustomers' => $payload->selectCustomers,
                        'customer_blacklist' => $payload->customer_blacklist ?? false
                    ], $voucher, $id));
                }
            }

            if (count($removeCustomers) > 0) {
                $customerIdsRemoveArray = array_chunk($removeCustomers, 2000);
                foreach ($customerIdsRemoveArray as $listIdsRemove) {
                    dispatch(new LinkVoucherClients([
                        'new_customers' => '',
                        'remove_customers' => $listIdsRemove,
                        'selectCustomers' => $payload->selectCustomers,
                        'customer_blacklist' => $payload->customer_blacklist ?? false
                    ], $voucher, $id));
                }
            }
        } else {
            if (isset($payload->typeIDCustomers) && in_array($payload->typeIDCustomers, ['adicionar', 'substituir'])) {
                $array = $payload->new_customers ?? [];
                if (isset($payload->groupIDCustomers)) {
                    $array = $payload->groupIDCustomers;
                }

                if (count($array) > 0) {
                    if ($payload->typeIDCustomers == "substituir") {
                        Voucher::find($id)->customers()->detach();
                    }

                    $array = Customer::select('id')->whereIn('id', array_unique($array))->get()->pluck('id')->toArray();
                    $customersIdsArray = array_chunk($array, 2000);
                    foreach ($customersIdsArray as $listCustomerIds) {
                        dispatch(new LinkVoucherClients([
                            'groupIDCustomers' => $listCustomerIds,
                            'selectCustomers' => $payload->selectCustomers,
                            'typeIDCustomers' => $payload->typeIDCustomers,
                            'customer_blacklist' => $payload->customer_blacklist ?? false
                        ], $voucher, $id));
                    }
                }
            }
        }
        // end clientes

        dispatch(new LinkVoucherProducts([
            'check_products_type' => $payload->check_products_type ?? false,
            'check_products' => $payload->check_products ?? false,
            'produtos' => $payload->produtos ?? [],
            'product_blacklist' => $payload->product_blacklist ?? false,
        ], $voucher));

        if (isset($payload->regioes)) {
            $voucher->firebase_topics()->sync($payload->regioes);
        }
        return $voucher;
    }
}
