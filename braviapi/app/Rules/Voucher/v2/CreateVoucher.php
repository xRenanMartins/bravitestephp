<?php

namespace App\Rules\Voucher\v2;

use App\Jobs\Vouchers\LinkVoucherClients;
use App\Jobs\Vouchers\LinkVoucherProducts;
use App\Jobs\Vouchers\LinkVoucherStore;
use App\Traits\DateTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Store;
use Packk\Core\Models\Voucher;

class CreateVoucher
{
    use DateTrait;

    public function execute($payload)
    {
        try {
            DB::beginTransaction();
            if (in_array($payload->type_discount, ['A', 'FRETE_FIXO', 'VALOR_MAXIMO', 'VISUAL', 'FRETE_GRATIS'])) {
                $discount = $payload->discount * 100;
            } else if (in_array($payload->type_discount, ['P', 'FRETE_PERCENTUAL'])) {
                $discount = $payload->discount;
            } else {
                $cash_back = $payload->discount;
                $discount = 0;
            }

            $firstPurchase = isset($payload->first_purchase) && $payload->first_purchase !== false;
            $regions = isset($payload->regions) && count($payload->regions) > 0 ? 'LISTA' : null;

            $voucher = Voucher::create([
                'domain_id' => $payload->domain_id,
                'chave' => Str::upper($payload->key),
                'desconto' => $discount,
                'valor_minimo' => $payload->min_value * 100,
                'tipo_desconto' => $payload->type_discount,
                'validade' => !empty($payload->validate) ? self::getFormattedDate($payload->validate) : null,
                'quantidade_total' => $payload->total_quantity ?? 0,
                'quantidade_por_usuario' => $payload->quantity_per_user ?? 0,
                'frete_gratis' => $payload->type_discount == "FRETE_GRATIS",
                'primeira_compra' => $firstPurchase,
                'regiao' => $regions,
                'cash_back' => $cash_back ?? 0,
                'limite_valor' => $payload->limit_value,
                'inicio' => !empty($payload->start) ? self::getFormattedDate($payload->start) : null,
                'tipo_loja' => $payload->store_type,
                'customer_group_id' => (isset($payload->customer_group) && $payload->customer_group != '') ? $payload->customer_group : null,
                'categorias' => $payload->categories ?? null,
                'franchise_id' => !empty($payload->franchise_id) ? $payload->franchise_id : null,
                'owner' => $payload->owner ?? (auth()->user()->email ?? null),
                'payment_type' => in_array($payload->payment_type, ['ONLINE', 'OFFLINE']) ? Str::upper($payload->payment_type) : null,
                'delivery_method' => in_array($payload->delivery_method, ['DELIVERY', 'TAKEOUT']) ? Str::upper($payload->delivery_method) : null,
                'description' => $payload->description,
                'observation' => $payload->observation,
                'time' => $payload->time,
                'cashback_validity' => $payload->type_discount == 'CB' ? $payload->cashback_validity : null,
                'recurrence_promotion' => $payload->recurrence_promotion ?? '0',
                'employee' => $payload->employee ?? "NO",
                'channels' => $payload->channels ?? 'TODOS',
                'device' => $payload->device ?? 'TODOS'
            ]);

            if (isset($payload->placement) && $payload->placement != -1) {
                $voucher->broadcast = $payload->placement;
                $voucher->save();
            }

            $arrayStores = $payload->new_stores ?? [];
            $array = $payload->new_customers ?? [];

            if (count($arrayStores) > 0) {
                $storesId = Store::select('id')->whereIn('id', array_unique($arrayStores))->get()->pluck('id')->toArray();
            }
            if (count($array) > 0) {
                $customersIds = Customer::select('id')->whereIn('id', array_unique($array))->get()->pluck('id')->toArray();
            }

            if (isset($payload->regions)) {
                $voucher->firebase_topics()->sync($payload->regions);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Stores
        if (isset($storesId)) {
            $storesIdArray = array_chunk($storesId, 2000);
            foreach ($storesIdArray as $listIds) {
                dispatch(new LinkVoucherStore($voucher, [
                    'stores' => $listIds,
                    'blacklist' => $payload->store_blacklist ?? false,
                ]));
            }
        }

        // Customers
        if (isset($customersIds)) {
            $customersIdsArray = array_chunk($customersIds, 2000);
            foreach ($customersIdsArray as $listCustomerIds) {
                dispatch(new LinkVoucherClients($voucher, [
                    'customers' => $listCustomerIds,
                    'blacklist' => $payload->customer_blacklist ?? false,
                ]));
            }
        }

        // Products
        if (isset($payload->new_products) && is_array($payload->new_products) && $payload->product_option !== 'disabled') {
            dispatch(new LinkVoucherProducts($voucher, [
                'products' => $payload->new_products,
                'blacklist' => $payload->product_blacklist ?? false,
                'type' => $payload->product_option
            ]));
        }

        return $voucher;
    }
}
