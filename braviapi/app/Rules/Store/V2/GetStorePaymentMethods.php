<?php

namespace App\Rules\Store\V2;

use Packk\Core\Models\PaymentMethod;
use Packk\Core\Models\Store;

class GetStorePaymentMethods
{
    private Store $store;
    private $formInputs = [];
    private $paymentMethods = [];
    private $selectedPaymentMethods = [];

    public function execute($storeId)
    {
        $this->store = Store::with(['payment_methods'])->findOrFail($storeId);
        $this->selectedPaymentMethods = collect($this->store->payment_methods);

        $this->paymentMethods = PaymentMethod::where('is_active', true)->where(function ($q) {
                $q->whereNull('block_domains')->orWhere('block_domains', 'NOT LIKE', "%[{$this->store->domain_id}]%");
            })->groupBy('label')->orderBy('label')->get();

        $this->paymentOnline();
        $this->paymentOffline();

        return ['options' => $this->formInputs, 'has_seller' => !empty($this->store->zoop_seller_id)];
    }

    private function paymentOnline()
    {
        $online = $this->paymentMethods->where('mode', 'ONLINE');

        if ($online->isEmpty() || empty($this->store->zoop_seller_id)) {
            $this->formInputs['ONLINE'] = [];
            return;
        }

        $credit = $online->where('type', 'CREDITO')->where('label', 'CARTAO_CREDITO')->first();
        if (!empty($credit)) {
            $this->formInputs['ONLINE']['CREDITO'] = [[
                'label' => $credit->name,
                'name' => strtolower($credit->label),
                'checked' => $this->selectedPaymentMethods->where('label', $credit->label)->where('pivot.is_active', 1)->isNotEmpty()
            ]];;
        }

        $vouchers = $online->where('type', 'VOUCHER');
        if ($vouchers->isNotEmpty()) {
            $this->formInputs['ONLINE']['VOUCHER'] = [];

            foreach ($vouchers as $method) {
                $pivotVoucher = $this->selectedPaymentMethods->where('label', $method->label)->where('pivot.is_active', 1)->first();

                $this->formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'is_input' => false,
                    'checked' => isset($pivotVoucher->pivot) ? $pivotVoucher->pivot->is_active : false
                ];
                $this->formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => 'Merchant Key',
                    'is_input' => true,
                    'name' => strtolower($method->label) . '_merchant_key',
                    'value' => isset($pivotVoucher->pivot) ? $pivotVoucher->pivot->merchant_key : ''
                ];
                $this->formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => 'Merchant Id',
                    'is_input' => true,
                    'name' => strtolower($method->label) . '_merchant_id',
                    'value' => isset($pivotVoucher->pivot) ? $pivotVoucher->pivot->merchant_id : ''
                ];
                $this->formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => 'Comissão',
                    'is_input' => true,
                    'name' => strtolower($method->label) . '_settings',
                    'value' => isset($pivotVoucher->pivot) && !empty($pivotVoucher->pivot->settings) ? $pivotVoucher->pivot->settings['commission'] : ''
                ];
            }
        }

        $money = $online->where('type', 'DINHEIRO')->where('label', 'DINHEIRO')->first();
        if (!empty($money)) {
            $this->formInputs['ONLINE']['DINHEIRO'] = [[
                'label' => $money->name,
                'name' => strtolower($money->label),
                'checked' => $this->selectedPaymentMethods->where('label', $money->label)->where('pivot.is_active', 1)->isNotEmpty()
            ]];
        }

        $debitOptions = $online->where('type', 'DEBITO')->where('label', 'CARTAO_MAQ_DEBITO');
        if ($debitOptions->isNotEmpty()) {
            $this->formInputs["ONLINE"]["MAQUINA_DEBITO"] = [];

            foreach ($debitOptions as $method) {
                $this->formInputs['ONLINE']['MAQUINA_DEBITO'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->where('pivot.is_active', 1)->isNotEmpty()
                ];
            }
        }

        $creditOptions = $online->where('type', 'CREDITO')->where('label', 'CARTAO_MAQ_CREDITO');
        if ($creditOptions->isNotEmpty()) {
            $this->formInputs["ONLINE"]["MAQUINA_CREDITO"] = [];

            foreach ($creditOptions as $method) {
                $this->formInputs['ONLINE']['MAQUINA_CREDITO'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->where('pivot.is_active', 1)->isNotEmpty()
                ];
            }
        }

        $stoneOptions = $online->where('type', 'DEBITO')->where('label', 'POS_STONE');
        if ($stoneOptions->isNotEmpty()) {
            $this->formInputs["ONLINE"]["MAQUINA_STONE"] = [];

            foreach ($stoneOptions as $method) {
                $this->formInputs['ONLINE']['MAQUINA_STONE'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->where('pivot.is_active', 1)->isNotEmpty()
                ];
            }
        }

        $appsOptions = $online->where('type', 'CREDITO')->whereIn('label', ['PICPAY', 'AME']);
        if ($appsOptions->isNotEmpty()) {
            $this->formInputs["ONLINE"]["CARTEIRA_DIGITAL"] = [];

            foreach ($appsOptions as $method) {
                $this->formInputs['ONLINE']['CARTEIRA_DIGITAL'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->where('pivot.is_active', 1)->isNotEmpty()
                ];
            }
        }

        $othersOptions = $online->whereIn('type', ['BOLETO', 'DEBITO'])->whereIn('label', ['FATURADO', 'PIX']);
        if ($othersOptions->isNotEmpty()) {
            $this->formInputs["ONLINE"]["OUTROS"] = [];

            foreach ($othersOptions as $method) {
                $this->formInputs['ONLINE']['OUTROS'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->where('pivot.is_active', 1)->isNotEmpty()
                ];
            }
        }
    }

    private function paymentOffline()
    {
        $paymentOff = $this->paymentMethods->where('mode', 'OFFLINE');
        if ($paymentOff->isEmpty() || !$this->store->is_marketplace) {
            $this->formInputs['OFFLINE'] = null;
            return;
        }

        $debitOptions = $paymentOff->where('type', 'DEBITO')->whereIn('label', ['MKT_VALE_VR', 'MKT_VALE_TICKET', 'MKT_VALE_SODEXO', 'MKT_VALE_ALELO']);
        if ($debitOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["VALE"] = [];

            foreach ($debitOptions as $method) {
                $this->formInputs['OFFLINE']['VALE'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }

        $debitOptions = $paymentOff->where('type', 'DEBITO')->whereNotIn('label', ['MKT_VALE_VR', 'MKT_VALE_TICKET', 'MKT_VALE_SODEXO', 'MKT_VALE_ALELO', 'MKT_PIX']);
        if ($debitOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["DEBITO"] = [];

            foreach ($debitOptions as $method) {
                $this->formInputs['OFFLINE']['DEBITO'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }

        $creditOptions = $paymentOff->where('type', 'CREDITO')->whereNotIn('label', ['MKT_PICPAY', 'MKT_PIX']);
        if ($creditOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["CREDITO"] = [];

            foreach ($creditOptions as $method) {
                $this->formInputs['OFFLINE']['CREDITO'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }

        $picpayOptions = $paymentOff->where('type', 'CARTEIRA_DIGITAL')->where('label', 'MKT_PICPAY');
        if ($picpayOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["CARTEIRA_DIGITAL"] = [];

            foreach ($picpayOptions as $method) {
                $this->formInputs['OFFLINE']['CARTEIRA_DIGITAL'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }

        $moneyOptions = $paymentOff->where('type', 'DINHEIRO')->where('label', 'MKT_DINHEIRO');
        if ($moneyOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["DINHEIRO"] = [];

            foreach ($moneyOptions as $method) {
                $this->formInputs['OFFLINE']['DINHEIRO'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }

        $boletoOptions = $paymentOff->where('type', 'BOLETO');
        if ($boletoOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["BOLETO"] = [];

            foreach ($boletoOptions as $method) {
                $this->formInputs['OFFLINE']['BOLETO'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }

        $pixOptions = $paymentOff->where('type', 'DEBITO')->where('label', 'MKT_PIX');
        if ($pixOptions->isNotEmpty()) {
            $this->formInputs["OFFLINE"]["OUTROS"] = [];

            foreach ($pixOptions as $method) {
                $this->formInputs['OFFLINE']['OUTROS'][] = [
                    'label' => $method->name,
                    'name' => strtolower($method->label),
                    'checked' => $this->selectedPaymentMethods->where('label', $method->label)->isNotEmpty()
                ];
            }
        }
    }
}