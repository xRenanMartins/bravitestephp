<?php

namespace App\Rules\Invoice;

use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Order;
use Packk\Core\Models\Service;
use Packk\Core\Models\Wallet;
use Packk\Core\Scopes\DomainScope;

class LoadInvoice
{
    // App\Rules\Admin\Invoice\LoadInvoice::teste()
    const hidden = [
        "metrica",
        "entrega_estimada",
        "localizacao_entregador",
        "cs_status",
        "label",
        "endereco_cliente_id",
        "cliente_id",
        "status_label",

    ];

    public static function marketTax($start_date, $end_date, $references = [], $domain_id = 1)
    {
        $query = Order::withoutGlobalScope(DomainScope::class)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where("taxa_servico_loja", ">", 0)
            ->where('pedidos.domain_id', $domain_id)
            ->where('estado', 'F');

        if (count($references) == 1) {
            $query->selectRaw("id as reference_id, 'pedido' as reference_provider, domain_id, pedidos.taxa_servico_loja/100 as amount, cliente_id as owner_id, 'cliente' as owner_provider, 0 as payout_amount");
            $query->whereIn("cliente_id", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("domain_id, SUM(taxa_servico_loja/100) as amount, cliente_id as owner_id, 'cliente' as owner_provider, SUM(0) as payout_amount")
                ->whereIn("cliente_id", $references)
                ->groupByRaw("domain_id, cliente_id, 'cliente'");
        } else {
            $query->selectRaw("domain_id, SUM(taxa_servico_loja/100) as amount, cliente_id as owner_id, 'cliente' as owner_provider, SUM(0) as payout_amount")
                ->groupByRaw("domain_id, cliente_id, 'cliente'");
        }

        return $query->get()->makeHidden(self::hidden)->toArray();
    }

    public static function serviceTax($start_date, $end_date, $references = [], $domain_id = 1)
    {

        $query = Service::withoutGlobalScope(DomainScope::class)
            ->join("clientes", "clientes.id", "favor.cliente_id")
            ->join("users", "users.id", "clientes.user_id")
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('estado', 'CONCLUIDO')
            ->whereNotNull('users.cpf')
            ->where('favores.domain_id', $domain_id)
            ->where('tipo_cliente', 'NORMAL');

        if (count($references) == 1) {
            $query->selectRaw("id as reference_id, 'favor' as reference_provider, domain_id, (valor - entregador_recebe)/100 as amount, cliente_id as owner_id, 'cliente' as owner_provider, 0 as payout_amount");
            $query->whereIn("cliente_id", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("domain_id, (valor - entregador_recebe)/100 as amount, cliente_id as owner_id, 'cliente' as owner_provider, SUM(0) as payout_amount")
                ->whereIn("cliente_id", $references)
                ->groupByRaw("domain_id, cliente_id, 'cliente'");
        } else {
            $query->selectRaw("domain_id, (valor - entregador_recebe)/100 as amount, cliente_id as owner_id, 'cliente' as owner_provider, SUM(0) as payout_amount")
                ->groupByRaw("domain_id, cliente_id, 'cliente'");
        }

        return $query->get()->toArray();
    }

    public static function conciergeTax($start_date, $end_date, $references = [], $domain_id = 1)
    {
        $query = Order::withoutGlobalScope(DomainScope::class)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where("tipo", "CONCIERGE")
            ->where('pedidos.domain_id', $domain_id)
            ->where('estado', 'F');

        if (count($references) == 1) {
            $query->selectRaw("pedidos.id as reference_id, 'pedido' as reference_provider , domain_id, comissao_concierge/100 as amount, cliente_id as owner_id, 'cliente' as owner_provider, 0 as payout_amount");
            $query->whereIn("cliente_id", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("domain_id, SUM(pedidos.comissao_concierge/100) as amount, cliente_id as owner_id, 'cliente' as owner_provider, SUM(0) as payout_amount")
                ->whereIn("cliente_id", $references)
                ->groupByRaw("domain_id, cliente_id, 'cliente'");
        } else {
            $query->selectRaw("domain_id, SUM(pedidos.comissao_concierge/100) as amount, cliente_id as owner_id, 'cliente' as owner_provider, SUM(0) as payout_amount")
                ->groupByRaw("domain_id, cliente_id, 'cliente'");
        }

        return $query->get()->makeHidden(self::hidden)->toArray();
    }

    public static function shopkeeperTax($start_date, $end_date, $references = [], $domain_id = 1)
    {
        $storesIdTest = DB::table('setting_store')->select('store_id')
            ->join('settings', 'settings.id', '=', 'setting_store.setting_id')
            ->where('settings.label', 'is_test')
            ->where('setting_store.value', 1)
            ->where('setting_store.is_active', 1)
            ->get()->pluck('store_id')->toArray();

        $query = Order::withoutGlobalScope(DomainScope::class)
            ->join("lojas", "lojas.id", "pedidos.loja_id")
            ->join("payment_methods", "payment_methods.id", "pedidos.payment_method_id")
            ->whereBetween('pedidos.created_at', [$start_date, $end_date])
            ->where('lojas.tax_model', '!=', 'NONE')
            ->whereNull('lojas.reference_provider')
            ->where('lojas.domain_id', $domain_id)
            ->whereRaw('LENGTH(lojas.cnpj) >= 14')
            ->where('estado', 'F')
            ->whereNotIn('pedidos.loja_id', $storesIdTest); // Ignora as lojas marcadas como teste

        $payout_select = "IF(lojas.franchise_id is null, ((creditos_payout/100) + (voucher_payout/100) + (products_combo_payout/100) + (takeout_payout/100) + (office_payout/100)),0)";

        $comissionFrachiseSelect = "IF(payment_methods.mode = 'ONLINE', coalesce(pedidos.mdr_percent, 2.3), 0)";
        $amount_select = "IF(lojas.franchise_id is null, (pedidos.valor / 100 * pedidos.comissao / 100), ((pedidos.valor / 100 * (pedidos.comissao - $comissionFrachiseSelect) / 100) / 2) + (pedidos.valor / 100 * $comissionFrachiseSelect / 100))";
        $query->whereRaw("{$amount_select} > 0");

        if (count($references) == 1) {
            $query->selectRaw("pedidos.id as reference_id,'pedido' as reference_provider, lojas.domain_id as domain_id, {$amount_select} as amount, loja_id as owner_id, 'loja' as owner_provider, {$payout_select} as payout_amount");
            $query->whereIn("lojas.cnpj", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("lojas.domain_id as domain_id, SUM({$amount_select}) as amount, lojas.cnpj as owner_id, 'cnpj' as owner_provider,SUM({$payout_select}) as payout_amount")
                ->whereIn("loja_id", $references)
                ->groupByRaw("domain_id, lojas.cnpj");
        } else {
            $query->selectRaw("lojas.domain_id as domain_id, SUM({$amount_select}) as amount, lojas.cnpj as owner_id, 'cnpj' as owner_provider,SUM({$payout_select}) as payout_amount")
                ->groupByRaw("domain_id, lojas.cnpj");
        }
        return $query->get()->makeHidden(self::hidden)->toArray();
    }

    public static function shopkeeperRetention($start_date, $end_date, $references = [], $domain_id = 1)
    {
        $query = Wallet::join("statements", function ($join) {
            $join->on('wallets.id', '=', 'statements.wallet_id');
            $join->on('wallets.owner_type', '=', "App\Models\Loja");
        })
            ->join("lojas", "lojas.id", "wallets.owner_id")
            ->whereBetween('statements.created_at', [$start_date, $end_date])
            ->where('lojas.tax_model', '!=', 'NONE')
            ->where('lojas.domain_id', $domain_id)
            ->whereRaw('LENGTH(lojas.cnpj) >= 14')
            ->where("tag", "VOUCHER_BENEFICIOS");

        if (count($references) == 1) {
            $query->selectRaw("statements.id as reference_id,'statement' as reference_provider,  lojas.domain_id as domain_id, statements.amount/100 as amount, wallets.owner_id as owner_id, 'loja' as owner_provider, 0 as payout_amount");
            $query->whereIn("wallets.owner_id", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("lojas.domain_id, SUM(statements.amount/100) as amount, wallets.owner_id as owner_id, 'loja' as owner_provider, SUM(0) as payout_amount")
                ->whereIn("wallets.owner_id", $references)
                ->groupByRaw("lojas.domain_id, wallets.owner_id, 'loja'");
        } else {
            $query->selectRaw("lojas.domain_id, SUM(statements.amount/100) as amount, wallets.owner_id as owner_id, 'loja' as owner_provider, SUM(0) as payout_amount")
                ->groupByRaw("lojas.domain_id, wallets.owner_id, 'loja'");
        }

        return $query->get()->toArray();
    }

    public static function shopkeeperAdhesion($start_date, $end_date, $references = [], $domain_id = 1)
    {
        $query = Wallet::join("statements", function ($join) {
            $join->on('wallets.id', '=', 'statements.wallet_id');
            $join->on('wallets.owner_type', '=', "App\Models\Loja");
        })
            ->join("lojas", "lojas.id", "wallets.owner_id")
            ->whereBetween('statements.created_at', [$start_date, $end_date])
            ->where('lojas.tax_model', '!=', 'NONE')
            ->where('lojas.domain_id', $domain_id)
            ->whereRaw('LENGTH(lojas.cnpj) >= 14')
            ->where("tag", "ADESAO");

        if (count($references) == 1) {
            $query->selectRaw("statements.id as reference_id, 'statement' as reference_provider,  lojas.domain_id as domain_id, statements.amount/100 as amount,  wallets.owner_id as owner_id, 'loja' as owner_provider, 0 as payout_amount");
            $query->whereIn("wallets.owner_id", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("lojas.domain_id, SUM(statements.amount/100) as amount, wallets.owner_id as owner_id, 'loja' as owner_provider, SUM(0) as payout_amount")
                ->whereIn("wallets.owner_id", $references)
                ->groupByRaw("lojas.domain_id, wallets.owner_id, 'loja'");
        } else {
            $query->selectRaw("lojas.domain_id, SUM(statements.amount/100) as amount, wallets.owner_id as owner_id, 'loja' as owner_provider, SUM(0) as payout_amount")
                ->groupByRaw("lojas.domain_id, wallets.owner_id, 'loja'");
        }

        return $query->get()->toArray();
    }

    public static function shopkeeperService($start_date, $end_date, $references = [], $domain_id = 1)
    {
        $query = Service::withoutGlobalScope(DomainScope::class)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('estado', 'CONCLUIDO')
            ->where('favores.domain_id', $domain_id)
            ->where('tipo_cliente', 'LOJISTA');

        if (count($references) == 1) {
            $query->selectRaw("favores.id as reference_id, 'favor' as reference_provider , domain_id, (valor - entregador_recebe)/100 as amount,  favores.cliente_id as owner_id, 'loja' as owner_provider, 0 as payout_amount");
            $query->whereIn("favores.cliente_id", $references);
        } else if (count($references) > 1) {
            $query->selectRaw("domain_id, SUM((valor - entregador_recebe)/100) as amount, favores.cliente_id as owner_id, 'loja' as owner_provider, SUM(0) as payout_amount")
                ->whereIn("favores.cliente_id", $references)
                ->groupByRaw("domain_id, favores.cliente_id, 'cliente'");
        } else {
            $query->selectRaw("domain_id, SUM((valor - entregador_recebe)/100) as amount, favores.cliente_id as owner_id, 'loja' as owner_provider, SUM(0) as payout_amount")
                ->groupByRaw("domain_id, favores.cliente_id, 'cliente'");
        }
        return $query->get()->toArray();
    }
}