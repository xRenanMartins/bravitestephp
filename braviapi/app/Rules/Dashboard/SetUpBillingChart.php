<?php

namespace App\Rules\Dashboard;

use Packk\Core\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Order;
use Packk\Core\Scopes\DomainScope;

class SetUpBillingChart
{
    private $query;
    private $startDate;
    private $finishDate;

    public function execute(Request $request)
    {
        $user = Auth::user();
        $franchise = $user->getFranchise();

        $newOrder = new Order();
        $newOrder->setConnection('lake');

        $storesQuery = $newOrder->newQuery()->withoutGlobalScope(DomainScope::class)
            ->join('lojas', 'lojas.id', '=', 'pedidos.loja_id')
            ->where('pedidos.estado', 'F');

        if ($user->isFranchiseOperator()) {
            $storesQuery->whereNotNull('lojas.franchise_id');
        }
        if (!empty($franchise)) {
            $storesQuery->where('lojas.franchise_id', $franchise->id);
        }
        if (!empty($request->start_date)) {
            $this->startDate = Carbon::createFromFormat('d/m/Y H:i', $request->start_date);
            $storesQuery->where('pedidos.created_at', '>=', $this->startDate);
        }
        if (!empty($request->end_date)) {
            $this->finishDate = Carbon::createFromFormat('d/m/Y H:i', $request->end_date);
            $storesQuery->where('pedidos.created_at', '<=', $this->finishDate);
        }
        if (!empty($request->stores)) {
            $storesQuery->whereIn('lojas.id', explode(',', $request->stores));
        }
        if ((!empty($request->city) && !empty($request->states)) || $request->need_address) {
            $storesQuery->join('enderecos', 'enderecos.loja_id', '=', 'lojas.id');

            if (!empty($request->city)) {
                $storesQuery->whereIn('enderecos.cidade', explode(',', $request->cities));
            }
            if (!empty($request->states)) {
                $storesQuery->whereIn('enderecos.state', explode(',', $request->states));
            }
        }
        if (!empty($request->categories) || $request->need_category) {
            $storesQuery->join('categoria_loja', 'categoria_loja.loja_id', '=', 'lojas.id')
                ->join('categorias', 'categoria_loja.categoria_id', '=', 'categorias.id');

            if (!empty($request->categories)) {
                $storesQuery->whereIn('categoria_loja.categoria_id', explode(',', $request->categories));
            }
        }

        $this->query = $storesQuery;
        return $storesQuery;
    }

    public function selectByStores()
    {
        $subquery = $this->query->selectRaw('lojas.nome, pedidos.valor, pedidos.id')
            ->groupBy(['pedidos.id', 'lojas.nome']);
        $sql = clone $subquery;

        return DB::connection('lake')->table(DB::raw("({$subquery->toSql()}) as sub"))
            ->mergeBindings($sql->getQuery())
            ->selectRaw('nome, sum(valor) as faturamento, count(id) as quantidade')
            ->groupBy('nome')->orderByDesc('faturamento')->limit(15)->get();
    }

    public function selectByCategories()
    {
        $subquery = $this->query->selectRaw('categorias.nome, pedidos.valor, pedidos.id')
            ->groupBy(['pedidos.id', 'categorias.nome']);
        $sql = clone $subquery;

        return DB::connection('lake')->table(DB::raw("({$subquery->toSql()}) as sub"))
            ->mergeBindings($sql->getQuery())
            ->selectRaw('nome, sum(valor) as faturamento, count(id) as quantidade')
            ->groupBy('nome')->orderByDesc('faturamento')->limit(15)->get();
    }

    public function selectByCities()
    {
        $subquery = $this->query->selectRaw('LOWER(enderecos.cidade) as nome, pedidos.valor, pedidos.id')
            ->groupBy(['pedidos.id', 'enderecos.cidade']);
        $sql = clone $subquery;

        return DB::connection('lake')->table(DB::raw("({$subquery->toSql()}) as sub"))
            ->mergeBindings($sql->getQuery())
            ->selectRaw('nome, sum(valor) as faturamento, count(id) as quantidade')
            ->groupBy('nome')->orderByDesc('faturamento')->limit(15)->get();
    }

    public function selectByProducts()
    {
        $subquery = $this->query
            ->join('produtos_vendidos', 'produtos_vendidos.pedido_id', '=', 'pedidos.id')
            ->join('produtos', 'produtos.id', '=', 'produtos_vendidos.produto_id')
            ->selectRaw('produtos.nome, pedidos.valor, pedidos.id')
            ->groupBy(['pedidos.id', 'produtos.nome']);
        $sql = clone $subquery;

        return DB::connection('lake')->table(DB::raw("({$subquery->toSql()}) as sub"))
            ->mergeBindings($sql->getQuery())
            ->selectRaw('nome, sum(valor) as faturamento, count(id) as quantidade')
            ->groupBy('nome')->orderByDesc('faturamento')->limit(15)->get();
    }

    public function selectResume()
    {
        $result = $this->query->selectRaw('valor')->groupBy('pedidos.id')->get();

        $sum = $result->sum('valor');
        $count = $result->count();
        return [
            'ticket' => $count > 0 ? ($sum / $count) : $sum,
            'faturamento' => $sum,
            'quantidade' => $count,
        ];
    }

    public function selectClients()
    {
        return [
            'quantidade_clientes' => $this->query->selectRaw('pedidos.cliente_id')
                ->groupBy('pedidos.cliente_id')->get()->count()
        ];
    }

    public function selectGrossRevenue()
    {
        $result = $this->query
            ->selectRaw('pedidos.id, pedidos.valor, SUBSTR(pedidos.created_at, 1, 10) created_day')
            ->get();

        $start = $this->startDate->copy();
        $end = $this->finishDate->copy();
        $data = collect([]);

        do {
            $datekey = $start->toDateString();
            [$thisday, $result] = $result->partition(function ($c) use ($datekey) {
                return $c['created_day'] == $datekey;
            });
            $data[$datekey] = $thisday;
            $start->addDay();
        } while ($start < $end);

        return [
            'labels' => $data->keys()->toArray(),
            'data' => $data->map(function ($p) {
                return $p->sum('valor') / 100;
            })->toArray()
        ];
    }

    public function selectNewClients()
    {
        $start = $this->startDate->copy();
        $end = $this->finishDate->copy();
        $novos_clientes = collect([]);

        $newClient = new Customer();
        $newClient->setConnection('lake');

        $clientes = $newClient->newQuery()
            ->selectRaw('id, SUBSTR(created_at, 1, 10) created_day')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        do {
            $datekey = $start->toDateString();
            [$thisday, $clientes] = $clientes->partition(function ($c) use ($datekey) {
                return $c['created_day'] == $datekey;
            });
            $novos_clientes[$datekey] = $thisday;
            $start->addDay();
        } while ($start < $end);

        return [
            'labels' => $novos_clientes->keys()->toArray(),
            'data' => $novos_clientes->map(function ($c) {
                return $c->count();
            })->toArray()
        ];
    }
}