<?php

namespace App\Rules\Voucher\v2;

use Carbon\Carbon;
use Packk\Core\Models\Product;
use Packk\Core\Models\Voucher;

class ShowVoucher
{
    public function execute($id)
    {
        $voucher = Voucher::findOrFail($id);

        $productsId = $voucher->products()->whereNotNull('product_id')->get(['product_id'])->pluck('product_id');
        $productsEan = $voucher->products()->whereNotNull('ean')->get(['ean'])->pluck('ean');
        $products = Product::query()->select(['id'])->whereIn('id', $productsId)->get();
        $products = $products->merge(Product::select(['id'])->whereIn('ean', $productsEan)->get())->pluck('id');

        if (in_array($voucher->tipo_desconto, ['A', 'FRETE_FIXO', 'VALOR_MAXIMO', 'VISUAL', 'FRETE_GRATIS'])) {
            $discount = $voucher->desconto / 100;
        } else if (in_array($voucher->tipo_desconto, ['P', 'FRETE_PERCENTUAL'])) {
            $discount = $voucher->desconto;
        } else if ($voucher->tipo_desconto == 'CB') {
            $discount = $voucher->cash_back;
        } else {
            $discount = 0;
        }

        return [
            'id' => $voucher->id,
            'domain_id' => $voucher->domain_id,
            'key' => $voucher->chave,
            'discount' => $discount,
            'min_value' => $voucher->valor_minimo / 100,
            'type_discount' => $voucher->tipo_desconto,
            'start' => !empty($voucher->inicio) ? Carbon::parse($voucher->inicio)->format('Y-m-d H:i:s') : null,
            'validate' => !empty($voucher->validade) ? Carbon::parse($voucher->validade)->format('Y-m-d H:i:s') : null,
            'total_quantity' => $voucher->quantidade_total,
            'quantity_per_user' => $voucher->quantidade_por_usuario,
            'free_delivery' => $voucher->frete_gratis,
            'first_purchase' => $voucher->primeira_compra,
            'cash_back' => $voucher->cash_back,
            'limit_value' => $voucher->limite_valor,
            'store_type' => $voucher->tipo_loja,
            'image' => $voucher->imagem,
            'customer_group_id' => $voucher->customer_group_id,
            'categories' => $voucher->categorias,
            'franchise_id' => $voucher->franchise_id,
            'owner' => $voucher->owner,
            'payment_type' => $voucher->payment_type,
            'delivery_method' => $voucher->delivery_method,
            'description' => $voucher->description,
            'observation' => $voucher->observation,
            'time' => $voucher->time,
            'cashback_validity' => $voucher->cashback_validity,
            'recurrence_promotion' => $voucher->recurrence_promotion,
            'employee' => $voucher->employee,
            'channels' => $voucher->channels,
            'device' => $voucher->device,
            'stores' => $voucher->stores()->limit(50)->get(['loja_id'])->pluck('loja_id'),
            'products' => $products,
            'customers' => $voucher->customers()->limit(50)->get(['clientes.id'])->pluck('id'),
            'regions' => $voucher->firebase_topics()->get(['firebase_topic_id'])->pluck('firebase_topic_id'),
            'has_more' => [
                'stores' => $voucher->stores()->count() > 50,
                'customers' => $voucher->customers()->count() > 50,
            ],
            'blacklist' => [
                'stores' => $voucher->stores()->where('blacklist', 1)->exists(),
                'customers' => $voucher->customers()->where('blacklist', 1)->exists(),
                'products' => $voucher->products()->where('blacklist', 1)->exists(),
            ],
            'breaks' => self::getBreaks($voucher)
        ];
    }

    public static function getBreaks(Voucher $voucher): array
    {
        $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $intervalDay = 48;
        $intervalHour = 2;
        $intervalMinute = 30;
        $hours = explode("\r\n", chunk_split($voucher->time, $intervalDay));
        $respBreaks = [];

        for ($i = 0; $i < sizeof($dias); $i++) {
            $breaks = collect([]);
            $ini = -1;
            if (isset($voucher->time) && strlen($voucher->time) > 0) {
                for ($j = 0; $j < $intervalDay; $j++) {
                    $index = $hours[$i];
                    if ($hours[$i][$j] == "1" && $ini == -1) {
                        $ini = $j;
                    }

                    if (($hours[$i][$j] == "0" && $ini != -1) || ($ini != -1 && $j == $intervalDay - 1)) {
                        $interval = new \stdClass();
                        $valueHourStart = $ini / $intervalHour;
                        $valueMinuteStart = $ini % $intervalHour;
                        $valueHourEnd = $j / $intervalHour;
                        $valueMinuteEnd = $j % $intervalHour;

                        if ($index[$intervalDay - 1] == 1) {
                            $valueHourEnd += 1;
                            $valueMinuteEnd = 0;
                        }

                        $interval->start = sprintf('%02d', $valueHourStart) . ":" . ($valueMinuteStart == 0 ? "00" : $valueMinuteStart * $intervalMinute);
                        $interval->end = sprintf("%02d", $valueHourEnd) . ":" . ($valueMinuteEnd == 0 ? "00" : ($valueMinuteEnd) * $intervalMinute);
                        $breaks->push($interval);
                        $ini = -1;
                    }
                }
            }

            $respBreaks[$i] = [
                'day' => $dias[$i],
                'hours' => $breaks
            ];
        }
        return $respBreaks;
    }
}