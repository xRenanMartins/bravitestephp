<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-06-30
 * Time: 12:29
 */

namespace App\Rules\Service;

use Packk\Core\Models\Service;
use Packk\Core\Models\Activity;
use Packk\Core\Util\Formatter;

class StoreBonus
{
    public function execute($payload, $favor_id, $user)
    {
        try {
            $favor = Service::findOrFail($favor_id);

            $taxa_bonus = str_replace(['R$', ' ', ','], '', $payload->taxa_bonus);

            $favor->entregador_recebe = ($favor->entregador_recebe - $favor->taxa_bonus) + $taxa_bonus;

            $favor->taxa_bonus = $taxa_bonus;

            $taxa_bonus_format = Formatter::money($taxa_bonus);
            $name = Formatter::nameUser($user);
            $favor->add_atividade(Activity::PEDIDO_ATIVIDADE_GENERICA,
                ['[::text]' => "Favor Bonificado pelo usuário {$name}. Valor: R$ {$taxa_bonus_format}"]);

            $favor->save();
            return [];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}