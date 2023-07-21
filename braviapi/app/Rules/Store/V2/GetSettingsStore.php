<?php

namespace App\Rules\Store\V2;

use App\Rules\Setting\SetStoreSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Permission;
use Packk\Core\Models\Store;

class GetSettingsStore
{
    public function execute($storeId = null)
    {
        $store = null;
        if (!empty($storeId)) {
            $store = Store::with(['shopkeeper'])->findOrFail($storeId);
            Cache::forget("store.{$storeId}.settings");
        }

        return [
            'logistic' => [
                [
                    'label' => 'Raio mínimo',
                    'setting' => 'raio_min',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_raio_min'),
                    'type' => 'number',
                    'value' => $store?->raio_min,
                    'inputs' => [],
                    'is_input' => true,
                    'is_store' => true,
                    'input_after_text' => 'km'
                ],
                [
                    'label' => 'Raio máximo',
                    'setting' => 'raio',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_raio'),
                    'type' => 'number',
                    'value' => $store?->raio,
                    'inputs' => [],
                    'is_input' => true,
                    'is_store' => true,
                    'input_after_text' => 'km'
                ],
                [
                    'label' => 'Tempo para bônus',
                    'setting' => 'deliveryman_automatic_bonus',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_time_bonus'),
                    'type' => 'number',
                    'value' => $store ? $store->getSetting('deliveryman_automatic_bonus') : null,
                    'inputs' => [],
                    'is_input' => true,
                    'input_after_text' => 'min'
                ],
                [
                    'label' => 'Tempo de tolerância',
                    'setting' => 'tolerance_timeout',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_time_tolerance'),
                    'type' => 'number',
                    'value' => $store?->getSetting('tolerance_timeout'),
                    'inputs' => [],
                    'is_input' => true,
                    'input_after_text' => 'min'
                ],
                [
                    'label' => 'Quantidade ligações entregador',
                    'setting' => 'deliveryman_call',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_deliveryman_call'),
                    'type' => 'number',
                    'value' => $store?->getSetting('deliveryman_call'),
                    'inputs' => [],
                    'is_input' => true
                ],
                [
                    'label' => 'Modo de delivery',
                    'setting' => 'loggi_delivery',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_mode_delivery'),
                    'type' => 'select',
                    'value' => $store ? $store->getSetting('loggi_delivery') : '0',
                    'inputs' => [],
                    'is_select' => true,
                    'options' => [
                        [
                            'id' => '0',
                            'title' => 'SHIPP'
                        ],
                        [
                            'id' => '1',
                            'title' => 'LOGGI'
                        ],
                    ]
                ],
                [
                    'label' => 'Ativar Escolha Veículo',
                    'setting' => 'choose_delivery_vehicle',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_choose_vehicle'),
                    'value' => $store ? $store->getSetting('choose_delivery_vehicle') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Frete Fixo Loja',
                    'setting' => 'input_show_delivery',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_frete_fixed'),
                    'value' => $store && !is_null($store->getSetting("delivery_fixed")),
                    'tooltip' => true,
                    'only_check' => true,
                    'description' => self::getTitleSetting('delivery_fixed'),
                    'inputs' => [
                        [
                            'label' => 'Valor do Frete',
                            'setting' => 'delivery_fixed',
                            'type' => 'money',
                            'value' => $store ? $store->getSetting('delivery_fixed') / 100 : 0,
                        ]
                    ]
                ],
                [
                    'label' => 'Ativar entregadores estrela',
                    'setting' => 'activate_star_deliveryman',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_star_deliveryman'),
                    'value' => $store ? $store->getSetting('activate_star_deliveryman') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Ativar bloqueio dispatch',
                    'setting' => 'dispatch_block',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_dispatch_block'),
                    'value' => $store ? $store->getSetting('dispatch_block') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Demanda entregador experiente',
                    'setting' => 'experienced_deliveryman',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_experienced_deliveryman'),
                    'value' => $store ? $store->getSetting('experienced_deliveryman') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Dispatch pedidos aprovados',
                    'setting' => 'dispatch_after_accepted',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_dispatch_approved_orders'),
                    'value' => $store ? $store->getSetting('dispatch_after_accepted') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Paga bônus automático',
                    'setting' => 'pay_automatic_bonus_fee',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_automatic_bonus_fee'),
                    'value' => $store ? $store->getSetting("pay_automatic_bonus_fee") : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Enviar pedido somente com entregador',
                    'setting' => 'aceite_sem_entregador',
                    'is_setting' => false,
                    'show' => true,
                    'shopkeeper' => true,
                    'disabled' => self::setDisabled('modify_only_with_courier'),
                    'value' => isset($store->shopkeeper) ? $store->shopkeeper->aceite_sem_entregador : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Pagamento de Entregas por Retenção',
                    'setting' => 'accept_payment_retention',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_payment_retention'),
                    'value' => $store ? $store->getSetting('accept_payment_retention') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Pós Picking',
                    'setting' => 'has_post_picking',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => $store['type'] == 'FULLCOMMERCE' ? true : false,
                    'value' => $store ? $store->getSetting('has_post_picking') : false,
                    'inputs' => []
                ],
            ],
            'operation' => [
                [
                    'label' => 'Valor máximo do pedido',
                    'setting' => 'max_value_order',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_max_value_order'),
                    'type' => 'money',
                    'value' => $store ? $store->getSetting('max_value_order') /100 : 200000,
                    'inputs' => [],
                    'is_input' => true,
                    'small' => true,
                    'description' => 'Limite de valor em um único pedido',
                ],
                [
                    'label' => 'Tempo para refazer pedido',
                    'setting' => 'remake_order',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_remake_order'),
                    'type' => 'number',
                    'value' => $store ? $store->getSetting('remake_order') : null,
                    'inputs' => [],
                    'is_input' => true,
                    'input_after_text' => 'min'
                ],
                [
                    'label' => 'Chat Pro',
                    'setting' => 'e_suporte_enterprise',
                    'is_setting' => false,
                    'show' => true,
                    'shopkeeper' => true,
                    'disabled' => self::setDisabled('modify_chat_pro'),
                    'value' => isset($store->shopkeeper) ? $store->shopkeeper->e_suporte_enterprise : false,
                    'inputs' => [],
                    'tooltip' => true,
                    'description' => 'Ativação de Chat Pro para a loja'
                ],
                [
                    'label' => 'Limite Itens Carrinho',
                    'setting' => 'input_cart_limit',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_cart_limit'),
                    'value' => $store && $store->getSetting("cart_limit") > 0,
                    'tooltip' => true,
                    'only_check' => true,
                    'description' => self::getTitleSetting('cart_limit'),
                    'inputs' => [
                        [
                            'label' => 'Quantidade',
                            'setting' => 'cart_limit',
                            'type' => 'number',
                            'value' => $store ? $store->getSetting('cart_limit') : 0,
                        ]
                    ]
                ],
                [
                    'label' => 'Retirada no local',
                    'setting' => 'takeout_discount',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_takeout_discount'),
                    'value' => $store ? $store->getSetting('takeout_discount') : true,
                    'inputs' => []
                ],
                [
                    'label' => 'Ligação automática',
                    'setting' => 'ligacao',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_call_automatic'),
                    'value' => $store && $store->getSetting('shopkeeper_call_max_attempts') > 0,
                    'tooltip' => true,
                    'only_check' => true,
                    'description' => self::getTitleSetting('shopkeeper_call_max_attempts'),
                    'inputs' => [
                        [
                            'label' => 'Tempo',
                            'setting' => 'shopkeeper_call_max_attempts',
                            'type' => 'number',
                            'value' => $store ? $store->getSetting('shopkeeper_call_max_attempts') : 0,
                            'input_after_text' => 'min'
                        ]
                    ]
                ],
                [
                    'label' => 'Agendamento de pedidos',
                    'setting' => 'is_scheduling',
                    'is_setting' => false,
                    'show' => false,
                    'disabled' => false,
                    'value' => isset($store->schedule) ? $store->schedule->is_scheduling : false,
                    'inputs' => [],
                    'tooltip' => true,
                    'description' => 'Possibilidade da loja programar horário de agendamento',
                ],
                [
                    'label' => 'Aprovação automática',
                    'setting' => 'input_automatic_accept',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_automatic_accept'),
                    'value' => $store && $store->getSetting('automatic_accept', 0) > 0,
                    'tooltip' => true,
                    'only_check' => true,
                    'description' => self::getTitleSetting('automatic_accept'),
                    'inputs' => [
                        [
                            'label' => 'Tempo',
                            'setting' => 'automatic_accept',
                            'type' => 'number',
                            'value' => $store ? $store->getSetting('automatic_accept') : 0,
                            'input_after_text' => 'min'
                        ]
                    ]
                ],
                [
                    'label' => 'Agrupar itens na impressão',
                    'setting' => 'store_product_print_aggregation',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_group_items_print'),
                    'value' => $store ? $store->getSetting('store_product_print_aggregation') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Envio mensagem whatsapp lojista',
                    'setting' => 'send_whatsapp_message_to_shopkepper',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_message_shopkeeper'),
                    'value' => $store ? $store->getSetting('send_whatsapp_message_to_shopkepper') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Frete por raio de entrega',
                    'setting' => 'input_ranged_discount_delivery',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_ranged_discount_delivery'),
                    'only_check' => true,
                    'value' => $store && !is_null($store->getSetting("ranged_discount_delivery")),
                    'inputs' => [
                        [
                            'label' => 'Configuração',
                            'setting' => 'ranged_discount_delivery',
                            'type' => 'text',
                            'value' => $store ? $store->getSetting('ranged_discount_delivery') : '',
                        ]
                    ]
                ],
                [
                    'label' => 'Frete por praça',
                    'setting' => 'input_ranged_discount_fee',
                    'is_setting' => true,
                    'show' => false,
                    'disabled' => self::setDisabled('modify_ranged_discount_fee'),
                    'only_check' => true,
                    'value' => $store && !is_null($store->getSetting("ranged_discount_fee")),
                    'inputs' => [
                        [
                            'label' => 'Configuração',
                            'setting' => 'ranged_discount_fee',
                            'type' => 'text',
                            'value' => $store ? self::formatFloat($store->getSetting('ranged_discount_fee')) : 0,
                        ]
                    ]
                ],
            ],
            'store' => [
                [
                    'label' => 'Ordem',
                    'setting' => 'ordem',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_ordem'),
                    'type' => 'number',
                    'value' => $store ? $store->ordem : 999,
                    'inputs' => [],
                    'is_input' => true,
                    'is_store' => true,
                    'small' => true,
                    'description' => 'Ordem de exibição da loja'
                ],
                [
                    'label' => 'Modo exibição',
                    'setting' => 'modo_exibicao',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_display_mode'),
                    'type' => 'select',
                    'value' => $store ? $store->modo_exibicao : 'G',
                    'inputs' => [],
                    'is_select' => true,
                    'options' => [
                        [
                            'id' => 'L',
                            'title' => 'Lista'
                        ],
                        [
                            'id' => 'G',
                            'title' => 'Grade'
                        ],
                    ]
                ],
                [
                    'label' => 'Retenção voucher',
                    'setting' => 'voucher_retention_percentage',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_voucher_retention'),
                    'type' => 'percent',
                    'value' => $store ? $store->getSetting('voucher_retention_percentage') : null,
                    'inputs' => [],
                    'is_input' => true,
                    'small' => true,
                    'description' => self::getTitleSetting('voucher_retention_percentage')
                ],
                [
                    'label' => 'Tag de filtro Popup',
                    'setting' => 'tag_popup',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_tag_popup'),
                    'type' => 'text',
                    'value' => $store ? $store->tag_popup : null,
                    'inputs' => [],
                    'is_input' => true,
                    'is_store' => true,
                ],
                [
                    'label' => 'Shipp Gold',
                    'setting' => 'is_gold',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_shipp_gold'),
                    'value' => $store ? $store->getSetting('is_gold') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'SKU Editavel',
                    'setting' => 'editable_sku',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_editable_sku'),
                    'value' => $store ? $store->getSetting('editable_sku') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Desconto Frete Loja',
                    'setting' => 'input_show_discount',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_discount_delivery'),
                    'only_check' => true,
                    'value' => $store && !is_null($store->getSetting("discount_delivery")),
                    'inputs' => [
                        [
                            'label' => 'Valor do desconto',
                            'setting' => 'discount_delivery',
                            'type' => 'money',
                            'value' => $store ? self::formatFloat($store->getSetting('discount_delivery')) : 0,
                        ]
                    ]
                ],
                [
                    'label' => 'Estoque',
                    'setting' => 'storage_active',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_storage_active'),
                    'value' => $store ? $store->getSetting("storage_active") : false,
                    'inputs' => [],
                    'tooltip' => true,
                    'description' => self::getTitleSetting('storage_active')
                ],
                [
                    'label' => 'Impressão de Cupom Ordenada',
                    'setting' => 'print_insumos_created_order',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_order_cupon'),
                    'value' => $store ? $store->getSetting('print_insumos_created_order') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Plano de recebimento',
                    'setting' => 'input_receive_after',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_receive_after'),
                    'tooltip' => true,
                    'only_check' => true,
                    'description' => self::getTitleSetting('receive_after'),
                    'value' => $store && !is_null($store->getSetting("receive_after")),
                    'inputs' => [
                        [
                            'label' => 'Tempo',
                            'setting' => 'receive_after',
                            'type' => 'number',
                            'value' => $store ? $store->getSetting('receive_after') : 0,
                            'input_after_text' => 'dias',
                        ]
                    ]
                ],
                [
                    'label' => 'Acesso antecipado à Plataforma do Lojista',
                    'setting' => 'early_access',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => true,
                    'value' => true,
                    'inputs' => [],
                    'tooltip' => true,
                    'description' => self::getTitleSetting('early_access')
                ],
                [
                    'label' => 'Taxa de Serviço',
                    'setting' => 'input_show_fee',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_service_fee'),
                    'only_check' => true,
                    'value' => $store && ($store->getSetting('highest_service_fee') +
                        $store->getSetting('minimum_service_fee')
                        + $store->getSetting('percentage_service_fee')) > 0,
                    'inputs' => [
                        [
                            'label' => 'Porcentagem',
                            'setting' => 'percentage_service_fee',
                            'type' => 'percent',
                            'value' => $store ? $store->getSetting('percentage_service_fee') : 0,
                        ],
                        [
                            'label' => 'Valor mín.',
                            'setting' => 'minimum_service_fee',
                            'type' => 'money',
                            'value' => $store ? $store->getSetting('minimum_service_fee') / 100 : 0,
                        ],
                        [
                            'label' => 'Valor máx.',
                            'setting' => 'highest_service_fee',
                            'type' => 'money',
                            'value' => $store ? $store->getSetting('highest_service_fee') / 100 : 0,
                        ]
                    ]
                ],
                [
                    'label' => 'Possui balança',
                    'setting' => 'has_weight_scale',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => $store['type'] == 'FULLCOMMERCE' ? true : false,
                    'value' => $store ? $store->getSetting('has_weight_scale') : false,
                    'inputs' => []
                ],

            ],
            'others' => [
                [
                    'label' => 'Regime tributário',
                    'setting' => 'tax_model',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => false,
                    'type' => 'text',
                    'value' => $store ? $store->tax_model : 'NONE',
                    'inputs' => [],
                    'is_select' => true,
                    'is_store' => true,
                    'options' => [
                        ['id' => 'NONE', 'title' => 'Nenhum'],
                        ['id' => 'SNN', 'title' => 'Simples'],
                        ['id' => 'NSN', 'title' => 'Não Simples'],
                        ['id' => 'MEI', 'title' => 'MEI']
                    ]
                ],
                [
                    'label' => 'Tipo de impressão cupom',
                    'setting' => 'store_print_order_type',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_order_type'),
                    'type' => 'select',
                    'value' => $store ? $store->getSetting('store_print_order_type') : 'SUMMARIZED',
                    'inputs' => [],
                    'is_select' => true,
                    'options' => [
                        [
                            'id' => 'SUMMARIZED',
                            'title' => 'Cupom Resumido'
                        ],
                        [
                            'id' => 'DETAILED',
                            'title' => 'Cupom Detalhado'
                        ],
                    ]
                ],
                [
                    'label' => 'Identificador para integração',
                    'setting' => 'integrations',
                    'is_setting' => true,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_integrations'),
                    'type' => 'text',
                    'value' => $store ? $store->getSetting('integrations') : null,
                    'inputs' => [],
                    'is_input' => true
                ],
                [
                    'label' => 'Identificador da marca',
                    'setting' => 'tag_marca',
                    'is_setting' => false,
                    'show' => true,
                    'is_store' => true,
                    'disabled' => self::setDisabled('modify_tag_marca'),
                    'type' => 'text',
                    'value' => $store?->tag_marca,
                    'inputs' => [],
                    'is_input' => true
                ],
                [
                    'label' => 'Plano premium',
                    'setting' => 'input_premium_at',
                    'is_setting' => false,
                    'show' => true,
                    'disabled' => self::setDisabled('modify_plan_premium'),
                    'value' => isset($store->shopkeeper) && !empty($store->shopkeeper->premium_at),
                    'only_check' => true,
                    'inputs' => [
                        [
                            'label' => "Data e Hora",
                            'setting' => 'premium_at',
                            'type' => 'datetime',
                            'shopkeeper' => true,
                            'value' => isset($store->shopkeeper) && !empty($store->shopkeeper->premium_at) ? Carbon::parse($store->shopkeeper->premium_at)->format('d/m/Y H:i') : '',
                        ],
                    ]
                ],
                [
                    'label' => 'Assinatura',
                    'setting' => 'signature',
                    'is_setting' => true,
                    'show' => $store && $store->domain->hasFeature('signature'),
                    'disabled' => self::setDisabled('modify_signature'),
                    'value' => $store ? $store->getSetting('signature') : false,
                    'inputs' => []
                ],
                [
                    'label' => 'Permitir Vínculo de Usuários (Connect dez)',
                    'setting' => 'binding_users',
                    'is_setting' => true,
                    'show' => $store && $store->domain->id == 4,
                    'disabled' => self::setDisabled('modify_binding_users'),
                    'value' => $store ? $store->getSetting('binding_users') : false,
                    'inputs' => []
                ],
            ],
        ];
    }

    private static function formatFloat($value)
    {
        return $value / 100;
    }

    private static function getTitleSetting(string $setting)
    {
        $settings = SetStoreSetting::getSettings();
        return $settings->where('label', $setting)->first()->name ?? '';
    }

    private static function setDisabled($permission)
    {
        $user = Auth::user();
        $hasPermission = Permission::where('key_system', 'admin')->where('key', $permission)->exists();

        if (!$hasPermission) {
            return false;
        } else if ($user->hasPermission($permission)) {
            return false;
        }
        return true;
    }
}
