<?php

namespace App\Http\Controllers;

use App\Rules\Dashboard\SetUpBillingChart;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Rules\Domain\ShowStats;
use Packk\Core\Models\Store;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->start_period == null || $request->start_period == '') {
            $request->start_period = Carbon::now()->format('d/m/Y');
        }
        if ($request->end_period == null || $request->end_period == '') {
            $request->end_period = Carbon::now()->format('d/m/Y');
        }
        if (!strstr($request->end_period, '/')) {
            $request->end_period = (new \Carbon\Carbon($request->end_period))->format('d/m/Y');
        }

//         return (new ShowStats())->execute($request->loja, $request->start_period, $request->end_period);
        return (new ShowStats())->mock();

    }

    public function autoComplete(Request $request)
    {
        return Store::where('nome', 'like', '%' . $request->nome . '%')
            ->select(DB::raw('nome'))
            ->get();
    }

    // Itens para filtragem
    public function filters(Request $request)
    {
        $user = Auth::user();
        $franchise = $user->getFranchise();

        $query = Store::query()
            ->join('enderecos', 'enderecos.loja_id', '=', 'lojas.id')
            ->join('categoria_loja', 'categoria_loja.loja_id', '=', 'lojas.id')
            ->join('categorias', 'categoria_loja.categoria_id', '=', 'categorias.id');

        if ($user->isFranchiseOperator()) {
            $query->whereNotNull('lojas.franchise_id');
        }
        if (!empty($franchise)) {
            $query->where('lojas.franchise_id', $franchise->id);
        }

        return [
            'stores' => (clone $query)->selectRaw('lojas.id, lojas.nome')
                ->groupBy(['lojas.id'])->get()->toArray(),
            'cities' => (clone $query)->selectRaw('LOWER(enderecos.cidade) as nome')
                ->groupBy(['enderecos.cidade'])->get()->toArray(),
            'states' => (clone $query)->selectRaw('LOWER(enderecos.state) as nome')
                ->groupBy(['enderecos.state'])->get()->toArray(),
            'categories' => (clone $query)->selectRaw('categorias.id, categorias.nome')
                ->groupBy(['categorias.id'])->get()->toArray(),
        ];
    }

    // Ranking de Lojas
    public function storeGraphic(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectByStores();
    }

    // Faturamento por Categoria
    public function categoryGraphic(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $request->merge(['need_category' => true]);
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectByCategories();
    }

    // Faturamento por Cidades
    public function citiesGraphic(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $request->merge(['need_address' => true]);
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectByCities();
    }

    // Ranking de Produtos
    public function productsGraphic(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectByProducts();
    }

    // Nº de clientes
    public function clientsNumber(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectClients();
    }

    // Faturamento, Nº de Pedidos e Ticket Médio
    public function resume(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectResume();
    }

    // Novos clientes
    public function newClients(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectNewClients();
    }

    // Faturamento bruto
    public function grossRevenue(Request $request, SetUpBillingChart $setUpBillingChart)
    {
        $setUpBillingChart->execute($request);
        return $setUpBillingChart->selectGrossRevenue();
    }
}