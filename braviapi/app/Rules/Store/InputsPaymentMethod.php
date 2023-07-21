<?php

namespace App\Rules\Store;

class InputsPaymentMethod
{
    private static $formInputs = [];

    public static function execute($paymentMethodsGroups, $store, $storeTypeCheck)
    {
        if ($store) {
            if ($store->type === "PARCEIRO_MARKETPLACE_EXCLUSIVO" || $store->type === "PARCEIRO_MARKETPLACE_NORMAL") {
                $storeTypeCheck = "MARKETPLACE";
            }
        }

        if (isset($paymentMethodsGroups['ONLINE'])) {
            static::$formInputs['ONLINE'] = [
                'CREDITO' => [],
                'VOUCHER' => [],
                'DINHEIRO' => [],
                'MAQUINA_DEBITO' => [],
                'MAQUINA_CREDITO' => [],
                'MAQUINA_STONE' => [],
                'CARTEIRA_DIGITAL' => [],
                'OUTROS' => [],
            ];

            self::paymentOnline($paymentMethodsGroups, $storeTypeCheck, $store);
        }
        if (isset($paymentMethodsGroups['OFFLINE'])) {
            static::$formInputs['OFFLINE'] = [
                'VALE' => [],
                'DEBITO' => [],
                'CREDITO' => [],
                'CARTEIRA_DIGITAL' => [],
                'DINHEIRO' => [],
                'OUTROS' => [],
            ];

            self::paymentOffline($paymentMethodsGroups, $storeTypeCheck, $store);
        }

        return static::$formInputs;
    }

    private static function paymentOnline($paymentMethodsGroups, $storeTypeCheck, $store)
    {
        if (isset($paymentMethodsGroups["ONLINE"]["CREDITO"])) {
            foreach ($paymentMethodsGroups["ONLINE"]["CREDITO"] as $method) {
                if ($method->label == 'CARTAO_CREDITO') {
                    static::$formInputs['ONLINE']['CREDITO'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active) || !isset($store),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["VOUCHER"])) {
            foreach ($paymentMethodsGroups["ONLINE"]["VOUCHER"] as $method) {
                static::$formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => $method->name,
                    'name' => 'form_payment[' . strtolower($method->label) . ']',
                    'data_name' => strtolower($method->label),
                    'radio' => 1,
                    'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                    'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                ];
                static::$formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => 'Merchant Key',
                    'radio' => 0,
                    'name' => 'form_payment[' . strtolower($method->label) . '][merchant_key]',
                    'data_name' => strtolower($method->label),
                    'data_sec' => strtolower($method->label) . '_merchant_key',
                    'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                    'value' => (isset($store) && isset($method->pivot) && $method->pivot->merchant_key) ? $method->pivot->merchant_key : '',
                ];
                static::$formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => 'Merchant Id',
                    'radio' => 0,
                    'name' => 'form_payment[' . strtolower($method->label) . '][merchant_id]',
                    'data_name' => strtolower($method->label),
                    'data_sec' => strtolower($method->label) . '_merchant_id',
                    'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                    'value' => (isset($store) && isset($method->pivot) && $method->pivot->merchant_id) ? $method->pivot->merchant_id : '',
                ];
                static::$formInputs['ONLINE']['VOUCHER'][] = [
                    'label' => 'Comissão',
                    'radio' => 0,
                    'name' => 'form_payment[' . strtolower($method->label) . '][settings]',
                    'data_name' => strtolower($method->label),
                    'data_sec' => strtolower($method->label) . '_settings',
                    'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                    'value' => $method->pivot->settings['commission'] ?? '',
                ];
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["DINHEIRO"])) {
            foreach ($paymentMethodsGroups["ONLINE"]["DINHEIRO"] as $method) {
                if ($method->label == 'DINHEIRO') {
                    $money_desc = (($storeTypeCheck == 'LOCAL') ? "Forma de pagamento válida somente para lojas parceiras. O usuário recebe o troco em créditos." : "A loja é responsável por enviar o troco necessário de acordo com o valor informado pelo cliente");
                    static::$formInputs['ONLINE']['DINHEIRO'][] = [
                        'title' => $money_desc,
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["DEBITO"]) && in_array("CARTAO_MAQ_DEBITO", array_column($paymentMethodsGroups["ONLINE"]["DEBITO"], 'label'))) {
            foreach ($paymentMethodsGroups["ONLINE"]["DEBITO"] as $method) {
                if ($method->label != 'POS_STONE') {
                    static::$formInputs['ONLINE']['MAQUINA_DEBITO'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["CREDITO"]) && in_array("CARTAO_MAQ_CREDITO", array_column($paymentMethodsGroups["ONLINE"]["CREDITO"], 'label'))) {
            foreach ($paymentMethodsGroups["ONLINE"]["CREDITO"] as $method) {
                if ($method->label == 'CARTAO_MAQ_CREDITO') {
                    static::$formInputs['ONLINE']['MAQUINA_CREDITO'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["DEBITO"]) && in_array("POS_STONE", array_column($paymentMethodsGroups["ONLINE"]["DEBITO"], 'label'))) {
            foreach ($paymentMethodsGroups["ONLINE"]["DEBITO"] as $method) {
                if ($method->label == 'POS_STONE') {
                    static::$formInputs['ONLINE']['MAQUINA_STONE'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["CREDITO"]) && in_array("PICPAY", array_column($paymentMethodsGroups["ONLINE"]["CREDITO"], 'label'))) {
            foreach ($paymentMethodsGroups["ONLINE"]["CREDITO"] as $method) {
                if (in_array($method->label, ['PICPAY', 'AME'])) {
                    static::$formInputs['ONLINE']['CARTEIRA_DIGITAL'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["BOLETO"]) && ((in_array("FATURADO", array_column($paymentMethodsGroups["ONLINE"]["BOLETO"], 'label'))) || (array_search("PIX", array_column($paymentMethodsGroups["ONLINE"]["BOLETO"], 'label')) !== FALSE))) {
            foreach ($paymentMethodsGroups["ONLINE"]["BOLETO"] as $method) {
                if ($method->label === 'FATURADO') {
                    static::$formInputs['ONLINE']['OUTROS'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["ONLINE"]["DEBITO"]) && in_array("PIX", array_column($paymentMethodsGroups["ONLINE"]["DEBITO"], 'label'))) {
            foreach ($paymentMethodsGroups["ONLINE"]["DEBITO"] as $method) {
                if ($method->label === 'PIX') {
                    static::$formInputs['ONLINE']['OUTROS'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->is_active),
                    ];
                }
            }
        }
    }

    private static function paymentOffline($paymentMethodsGroups, $storeTypeCheck, $store)
    {
        if (isset($paymentMethodsGroups["OFFLINE"]['DEBITO']) && ((in_array("MKT_VALE_VR", array_column($paymentMethodsGroups["OFFLINE"]["DEBITO"], 'label'))))) {
            foreach ($paymentMethodsGroups["OFFLINE"]['DEBITO'] as $method) {
                if (in_array($method->label, ['MKT_VALE_VR', 'MKT_VALE_TICKET', 'MKT_VALE_SODEXO', 'MKT_VALE_ALELO'])) {
                    static::$formInputs['OFFLINE']['VALE'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["OFFLINE"]["DEBITO"])) {
            foreach ($paymentMethodsGroups["OFFLINE"]["DEBITO"] as $method) {
                if ($method->label !== 'MKT_PIX' && (!in_array($method->label, ['MKT_VALE_VR', 'MKT_VALE_TICKET', 'MKT_VALE_SODEXO', 'MKT_VALE_ALELO']))) {
                    static::$formInputs['OFFLINE']['DEBITO'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["OFFLINE"]["CREDITO"])) {
            foreach ($paymentMethodsGroups["OFFLINE"]["CREDITO"] as $method) {
                if ($method->label != 'MKT_PICPAY' && $method->label != 'MKT_PIX') {
                    static::$formInputs['OFFLINE']['CREDITO'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["OFFLINE"]['CREDITO']) && in_array("MKT_PICPAY", array_column($paymentMethodsGroups["OFFLINE"]["CREDITO"], 'label'))) {
            foreach ($paymentMethodsGroups["OFFLINE"]['CREDITO'] as $method) {
                if ($method->label == 'MKT_PICPAY') {
                    static::$formInputs['OFFLINE']['CARTEIRA_DIGITAL'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                    ];
                }
            }
        }
        if (isset($paymentMethodsGroups["OFFLINE"]['DINHEIRO']) && in_array("MKT_DINHEIRO", array_column($paymentMethodsGroups["OFFLINE"]["DINHEIRO"], 'label'))) {
            foreach ($paymentMethodsGroups["OFFLINE"]["DINHEIRO"] as $method) {
                $money_desc = (($storeTypeCheck == 'LOCAL') ? "Forma de pagamento válida somente para lojas parceiras. O usuário recebe o troco em créditos." : "A loja é responsável por enviar o troco necessário de acordo com o valor informado pelo cliente");
                static::$formInputs['OFFLINE']['DINHEIRO'][] = [
                    'title' => $money_desc,
                    'label' => $method->name,
                    'name' => 'form_payment[' . strtolower($method->label) . ']',
                    'data_name' => strtolower($method->label),
                    'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                    'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                ];
            }
        }
        if (isset($paymentMethodsGroups["OFFLINE"]['BOLETO'])) {
            foreach ($paymentMethodsGroups["OFFLINE"]['BOLETO'] as $method) {
                static::$formInputs['OFFLINE']['OUTROS'][] = [
                    'label' => $method->name,
                    'name' => 'form_payment[' . strtolower($method->label) . ']',
                    'data_name' => strtolower($method->label),
                    'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                    'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                ];
            }
        }
        if (isset($paymentMethodsGroups["OFFLINE"]['DEBITO'])) {
            foreach ($paymentMethodsGroups["OFFLINE"]['DEBITO'] as $method) {
                if ($method->label == 'MKT_PIX') {
                    static::$formInputs['OFFLINE']['OUTROS'][] = [
                        'label' => $method->name,
                        'name' => 'form_payment[' . strtolower($method->label) . ']',
                        'data_name' => strtolower($method->label),
                        'block_domains' => !empty($method->block_domains) ? explode(',', $method->block_domains) : [],
                        'checked' => (isset($store) && isset($method->pivot) && $method->pivot->deleted_at === null),
                    ];
                }
            }
        }
    }
}
