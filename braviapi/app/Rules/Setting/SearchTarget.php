<?php

namespace App\Rules\Setting;

use Illuminate\Support\Facades\DB;

class SearchTarget
{
    public function execute($payload, $setting_id, $tag)
    {
        switch ($tag) {
            case 'customer':
                $query = DB::table('clientes AS c')
                    ->select('c.id', DB::raw('CONCAT(c.id, " - ", users.nome) AS title'), DB::raw('IF((SELECT count(*) 
                                        FROM setting_customer sc 
                                        WHERE sc.customer_id = c.id AND sc.setting_id = ? AND sc.is_active = 1) > 0, 1, 0) AS feature_enabled'))
                    ->join('users', 'users.id', '=', 'c.user_id')
                    ->setBindings([$setting_id])
                    ->orderBy('title', 'ASC');

                if (is_numeric($payload['q'])) {
                    $query->where('c.id', $payload['q']);
                } else {
                    if (is_string($payload['q'])) {
                        $query->where(DB::raw('CONCAT(c.id, " - ", users.nome)'), 'LIKE', '%' . $payload['q'] . '%')->take(10);
                    }
                }

                $lines = $query->get();
                break;
            case 'domain':
                $query = DB::table('domains AS d')
                    ->select('d.id', DB::raw('CONCAT(d.id, " - ", d.title) AS title'), DB::raw('IF((SELECT count(*) 
                                        FROM setting_domain sd 
                                        WHERE sd.domain_id = d.id AND sd.setting_id = ? AND sd.is_active = 1) > 0, 1, 0) AS feature_enabled'))
                    ->setBindings([$setting_id])
                    ->orderBy('d.title', 'ASC');

                if (is_numeric($payload['q'])) {
                    $query->where('d.id', $payload['q']);
                } else {
                    if (is_string($payload['q'])) {
                        $query->where('d.title', 'LIKE', '%' . $payload['q'] . '%')->take(10);
                    }
                }

                $lines = $query->get();
                break;

            case 'product':
                $query = DB::table('produtos AS p')
                    ->select('p.id', DB::raw('CONCAT(p.id, " - ", p.nome, " (", l.nome, ")") AS title'), DB::raw('IF((SELECT count(*) 
                                        FROM setting_product sp 
                                        WHERE sp.product_id = p.id AND sp.setting_id = ? AND sp.is_active = 1) > 0, 1, 0) AS feature_enabled'))
                    ->setBindings([$setting_id])
                    ->join('categorias AS c', 'p.categoria_id', '=', 'c.id')
                    ->join('lojas AS l', 'c.loja_id', '=', 'l.id')
                    ->orderBy('p.nome', 'ASC');

                if (is_numeric($payload['q'])) {
                    $query->where('p.id', $payload['q']);
                } else {
                    if (is_string($payload['q'])) {
                        $query->where('p.nome', 'LIKE', '%' . $payload['q'] . '%')->take(10);
                    }
                }

                $lines = $query->get();
                break;

            case 'shift':
                $query = DB::table('turnos AS t')
                    ->select('t.id', DB::raw('CONCAT("id:", t.id, " / valor:", t.valor, " / vagas:", t.vagas, " / regiao:", t.regiao) AS title'), DB::raw('IF((SELECT count(*) 
                                        FROM setting_shifts ss 
                                        WHERE ss.shift_id = t.id AND ss.setting_id = ? AND ss.is_active = 1) > 0, 1, 0) AS feature_enabled'))
                    ->setBindings([$setting_id])
                    ->orderBy('t.id', 'ASC');

                if (is_string($payload['q'])) {
                    $query->where(DB::raw('CONCAT("id:", t.id, " / valor:", t.valor, " / vagas:", t.vagas, " / regiao:", t.regiao)'), 'LIKE', '%' . $payload['q'] . '%')->take(10);
                }

                $lines = $query->get();
                break;

            case 'showcase':
                $query = DB::table('vitrines AS v')
                    ->select('v.id', DB::raw('CONCAT(v.id, " - ", v.titulo) AS title'), DB::raw('IF((SELECT count(*) 
                                        FROM setting_showcase ss 
                                        WHERE ss.showcase_id = v.id AND ss.setting_id = ? AND ss.is_active = 1) > 0, 1, 0) AS feature_enabled'))
                    ->setBindings([$setting_id])
                    ->orderBy('v.titulo', 'ASC');

                if (is_numeric($payload['q'])) {
                    $query->where('v.id', $payload['q']);
                } else {
                    if (is_string($payload['q'])) {
                        $query->where('v.titulo', 'LIKE', '%' . $payload['q'] . '%')->take(10);
                    }
                }

                $lines = $query->get();
                break;

            case 'store':
                $query = DB::table('lojas AS l')
                    ->select('l.id', DB::raw('CONCAT(l.id, " - ", l.nome) AS title'), DB::raw('IF((SELECT count(*) 
                                        FROM setting_store ss 
                                        WHERE ss.store_id = l.id AND ss.setting_id = ? AND ss.is_active = 1) > 0, 1, 0) AS feature_enabled'))
                    ->setBindings([$setting_id])
                    ->orderBy('l.nome', 'ASC');

                if (is_numeric($payload['q'])) {
                    $query->where('l.id', $payload['q']);
                } else {
                    if (is_string($payload['q'])) {
                        $query->where('l.nome', 'LIKE', '%' . $payload['q'] . '%')->take(10);
                    }
                }

                $lines = $query->get();
                break;
        }
        return $lines;
    }
}