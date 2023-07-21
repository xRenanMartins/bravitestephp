<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Rules\Admin\Maps\StoreMaps;
use Packk\Core\Actions\Admin\Delivery\Map\Deliverymen;
use Packk\Core\Models\Deliveryman as DeliverymanModel;
use Packk\Core\Models\AreaServed;

class MapController extends Controller
{
    public function stores(Request $request){
        $domain_id = $request->domain_id;

        return Cache::remember("domain.{$domain_id}:lojas_mapa", 60, function () use ($request) {
            return StoreMaps::execute($request->domain_id);
        });
    }
    public function deliverymen(Request $request){
        return Deliverymen::index();
    }
    public function stats(Request $request)
    {
        $teste = 0;
        return DeliverymanModel::stats($request->region);
    }

    public function deliveries(){

        // TODO: método obsoleto. código mantido para futura referência e compatibilidade
        return [];

        // $last_hours= Carbon::now()->subHours(12);

        // $entregas = Entrega::where('estado','!=','C')
        //     ->where('entregador_id','!=','null')
        //     ->where('created_at','>',$last_hours)
        //     ->get();
        // $jo = [];

        // foreach ($entregas as $entrega) {

        //     $d = $entrega->pedido->enderecoCliente;
        //     if($entrega->pedido->tipo == "CONCIERGE"){
        //         $o = $entrega->pedido->addressConcierge;
        //     }else{

        //         $o = $entrega->pedido->loja->enderecos[0];
        //     }
        //     $caminho = [];

        //     array_push($jo,[
        //         'entrega_id' => $entrega->id,
        //         'loja_id' => $entrega->pedido->loja_id,
        //         'entregador_id' => $entrega->entregador_id,
        //         'cliente_id' => $entrega->pedido->cliente_id,
        //         'estado' => $entrega->estado,
        //         'caminho' => $caminho
        //     ]);
        // }

        // $favores = Favor::where('estado','!=','CONCLUIDO')
        //     ->where('estado','!=','CANCELADO')
        //     ->where('entregador_id','!=','null')
        //     ->where('created_at','>',$last_hours)
        //     ->get();

        // foreach ($favores as $favor) {

        //     $caminho = [];

        //     array_push($jo,[
        //         'entrega_id' => $favor->id,
        //         'loja_id' => 0,
        //         'entregador_id' => $favor->entregador_id,
        //         'cliente_id' => $favor->cliente_id,
        //         'estado' => $favor->estado,
        //         'caminho' => $caminho
        //     ]);
        // }

        // return $jo;
    }
    public function region(Request $request){
        $region = AreaServed::posicaoRegioesAntendidas($request);
        return [$region];
    }

}