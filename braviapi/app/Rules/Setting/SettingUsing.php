<?php

namespace App\Rules\Setting;

use Packk\Core\Models\Domain;
use Packk\Core\Models\Store;
use Packk\Core\Models\Product;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\Shift;
use Packk\Core\Models\Customer;
use Packk\Core\Models\User;
use Illuminate\Support\Facades\DB;

class SettingUsing
{
    public function execute($payload, $feature_id, $tag)
    {
        $exists = collect([]);
        switch ($tag) {
            case 'customer':
                $lines = Customer::withoutGlobalScope('App\Scopes\DomainScope')
                    ->join('users', 'users.id', '=', 'clientes.user_id')
                    ->select('clientes.id', DB::raw('CONCAT(clientes.id, " - ", users.nome) AS title'))
                    ->like(DB::raw('CONCAT(clientes.id, " - ", users.nome)'), $payload["search"])
                    ->simplePaginate(20);
                $exists = DB::table('setting_customer')->where("setting_id", $feature_id)
                    ->whereIn("customer_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->selectRaw("customer_id as id")->get();
                break;
            case 'domain':
                $lines = Domain::withoutGlobalScope('App\Scopes\DomainScope')
                    ->select('domains.id', DB::raw('CONCAT(domains.id, " - ", domains.title) AS title'))
                    ->simplePaginate(20);
                $exists = DB::table('setting_domain')->where("setting_id", $feature_id)
                    ->whereIn("domain_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->selectRaw("domain_id as id")->get();
                break;
            case 'product':
                $lines = Product::withoutGlobalScope('App\Scopes\DomainScope')
                    ->join('lojas', 'produtos.store_id', '=', 'lojas.id')
                    ->select('produtos.id', DB::raw('CONCAT(produtos.id, " - ", produtos.nome, " (", lojas.nome, ")") AS title'))
                    ->like(DB::raw('CONCAT(produtos.id, " - ", produtos.nome, " (", lojas.nome, ")")'), $payload["search"])
                    ->simplePaginate(20);
                $exists = DB::table('setting_product')->where("setting_id", $feature_id)
                    ->whereIn("product_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->selectRaw("product_id as id")->get();
                break;
            case 'shift':
                $lines = Shift::withoutGlobalScope('App\Scopes\DomainScope')
                    ->select('turnos.id', DB::raw('CONCAT("id:", turnos.id, " / valor:", turnos.valor, " / vagas:", turnos.vagas, " / regiao:", turnos.regiao) AS title'))
                    ->like(DB::raw('CONCAT("id:", turnos.id, " / valor:", turnos.valor, " / vagas:", turnos.vagas, " / regiao:", turnos.regiao)'), $payload["search"])
                    ->simplePaginate(20);

                $exists = DB::table('setting_shifts'
                )->where("setting_id", $feature_id)
                ->whereIn("shift_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                ->selectRaw("shift_id as id")->get();
                break;
            case 'showcase':
                $lines = Showcase::withoutGlobalScope('App\Scopes\DomainScope')
                    ->select('vitrines.id', DB::raw('CONCAT(vitrines.id, " - ", vitrines.titulo) AS title'))
                    ->like(DB::raw('CONCAT(vitrines.id, " - ", vitrines.titulo)'), $payload["search"])
                    ->simplePaginate(20);
                $exists = DB::table('setting_showcase')->where("setting_id", $feature_id)
                    ->whereIn("showcase_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->selectRaw("showcase_id as id")->get();
                break;
            case 'store':
                $lines = Store::withoutGlobalScope('App\Scopes\DomainScope')
                    ->select('lojas.id', DB::raw('CONCAT(lojas.id, " - ", lojas.nome) AS title'))
                    ->like(DB::raw('CONCAT(lojas.id, " - ", lojas.nome)'), $payload["search"])
                    ->simplePaginate(20);
                $exists = DB::table('setting_store')->where("setting_id", $feature_id)
                    ->whereIn("store_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->selectRaw("store_id as id")->get();
                break;
            case 'zone':
                $lines = AreaServed::withoutGlobalScope('App\Scopes\DomainScope')
                    ->select('zonas_atendidas.id', DB::raw('CONCAT(zonas_atendidas.id, " - ", zonas_atendidas.identificador, " - ", zonas_atendidas.cidade) AS title'))
                    ->like(DB::raw('CONCAT(zonas_atendidas.id, " - ", zonas_atendidas.identificador, " - ", zonas_atendidas.cidade)'), $payload["search"])
                    ->simplePaginate(20);
                $exists = DB::table('setting_service_area')->where("setting_id", $feature_id)
                    ->whereIn("service_area_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->select("service_area_id as id")->get();
                break;
            case 'user':
                $lines = User::withoutGlobalScope('App\Scopes\DomainScope')
                    ->select('id', DB::raw('CONCAT(id, " - ", nome) AS title'))
                    ->like(DB::raw('CONCAT(id, " - ", nome)'), $payload["search"])
                    ->simplePaginate(20);
                $exists = DB::table('setting_user')->where("setting_id", $feature_id)
                    ->whereIn("user_id", $lines->getCollection()->pluck("id"))->where("is_active", 1)
                    ->select("user_id as id")->get();
                break;
        }
        $items = $lines->getCollection()->map(function ($item) use ($exists) {
            $item->feature_enabled = $exists->where("id",$item->id)->count() > 0;
            return $item;
        });
        $lines->setCollection($items);
        return $lines;
    }
}