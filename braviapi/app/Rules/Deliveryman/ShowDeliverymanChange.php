<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-08-04
 * Time: 15:52
 */

namespace App\Rules\Deliveryman;

use Packk\Core\Models\Deliveryman;

class ShowDeliverymanChange
{
    public function execute($payload)
    {
        $pesquisa = $payload->pesquisa_entregador;
        $entregadoresSelecionados = explode(",", $payload->entregadores);
        array_pop($entregadoresSelecionados);

        if (is_numeric($pesquisa)) {
            $entregadores = Deliveryman::select("entregadores.id as entregador_id", "users.telefone")
                ->selectRaw("concat(users.nome, ' ', users.sobrenome) as nome")
                ->join('users', 'users.id', '=', 'entregadores.user_id')
                ->where('entregadores.id', $pesquisa)
                ->orWhere('users.telefone', $pesquisa)
                ->get();
        } else {
            $entregadores = Deliveryman::select("entregadores.id as entregador_id", "users.telefone")
                ->selectRaw("concat(users.nome, ' ', users.sobrenome) as nome")
                ->join('users', 'users.id', '=', 'entregadores.user_id')
                ->where('users.nome', 'like', "%{$pesquisa}%")
                ->orWhere('users.sobrenome', 'like', "%{$pesquisa}%")
                ->orWhere('users.email', 'like', "%{$pesquisa}%")
                ->get();
        }

        foreach ($entregadores as $key => $entregador) {
            $entregador->telefone = $this->formatarTelefone($entregador->telefone);
            foreach ($entregadoresSelecionados as $idEntregador) {
                if ($entregador->entregador_id == $idEntregador) {
                    unset($entregadores[$key]);
                }
            }
        }

        if (isset($payload->type) && $payload->type == "R") {
            return ['entregadores' => $entregadores, 'click' => $payload->click];
        } else {
            return ['entregadores' => $entregadores];
        }
    }

    private function formatarTelefone($telefone)
    {
        $telefoneFormatado = preg_replace('/[^0-9]/', '', $telefone);
        $splitTelefone = [];
        if (strlen($telefone) == 11) {
            preg_match('/^([0-9]{2})([0-9]{4,5})([0-9]{4})$/', $telefoneFormatado, $splitTelefone);
        } else if (strlen($telefone) == 12) {
            preg_match('/^([0-9]{3})([0-9]{4,5})([0-9]{4})$/', $telefoneFormatado, $splitTelefone);
        }

        if ($splitTelefone) {
            return '(' . $splitTelefone[1] . ') ' . $splitTelefone[2] . '-' . $splitTelefone[3];
        }

        return $telefone;
    }
}