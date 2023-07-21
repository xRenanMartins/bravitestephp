<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\ServiceEvaluation;

class FeedbackController extends Controller {

    public function index(Request $request)
    {
        $id      = $request->id;
        $name    = $request->name;

        $avaliations = ServiceEvaluation::query()
        ->join('pedidos','pedidos.id','=','avaliacoes_servico.pedido_id')
        ->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')
        ->join('users', 'users.id', '=', 'clientes.user_id')
        ->join('lojas', 'lojas.id', '=', 'pedidos.loja_id')
        ->join('entregas', 'entregas.pedido_id', '=', 'pedidos.id', )
        ->join('entregadores', 'entregadores.id', '=', 'entregas.entregador_id')
        ->join('users AS usersentregador', 'usersentregador.id', '=', 'entregadores.user_id')
        ->select( "avaliacoes_servico.*", "lojas.nome as lojas_name", 'entregas.entregador_id')
        ->selectRaw('CONCAT(users.nome, " ", users.sobrenome) AS full_name')
        ->selectRaw('CONCAT(usersentregador.nome, " ", usersentregador.sobrenome) AS full_name_deliveryman')
        ->where('avaliacoes_servico.pedido_id', 'like',  '%'.$id)
        ->where('users.nome', 'like', '%'.$name.'%')
        ->orderBy('avaliacoes_servico.created_at', 'desc')
        ->simplePaginate($request->length);

        return $avaliations;
    }

}