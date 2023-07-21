<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 12:23
 */

namespace App\Rules\Order;

use Packk\Core\Models\Order;
use Packk\Core\Models\Activity;
use Packk\Core\Util\Formatter;

class StoreBonus
{
    public function execute($payload, $pedido_id, $user)
    {
        try {
            $pedido = Order::findOrFail($pedido_id);

            $taxa_bonus = str_replace(['R$', ' ', ','], '', $payload->taxa_bonus);

            $entrega = $pedido->entrega;

            $entrega->taxa_entrega = ($entrega->taxa_entrega - $entrega->taxa_bonus) + $taxa_bonus;

            $entrega->taxa_bonus = $taxa_bonus;
            $taxa_bonus_format = Formatter::money($taxa_bonus);
            $name = Formatter::nameUser($user);
            $pedido->add_atividade(Activity::PEDIDO_ATIVIDADE_GENERICA,
                ['[::text]' => "Pedido Bonificado pelo usuário {$name}. Valor: R$ {$taxa_bonus_format}"]);

            $entrega->save();
            return [];
        } catch (\Exception $e) {
            throw $e;
        }


    }
}