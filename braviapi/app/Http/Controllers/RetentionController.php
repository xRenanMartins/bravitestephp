<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Retention;
use Illuminate\Support\Facades\DB;

class RetentionController extends Controller
{
    public function index(Request $request)
    {
        $name = $request->name;
        $date = $request->date;
        $domain = $request->domain;
        $type = $request->type;
        $state = $request->state;

        return Retention::join('entregadores', 'entregadores.id', '=', 'retencoes.entregador_id')
            ->join('users', 'users.id', '=', 'entregadores.user_id')
            ->where('users.nome', 'like', '%' . $name . '%')
            ->where('retencoes.tipo', 'like', '%'.$type.'%')
            ->where('retencoes.estado', 'like', '%'.$state.'%')
            ->where('retencoes.comeca_em', 'like', '%' . $date . '%')
            ->where('retencoes.domain_id', 'like', '%' . $domain . '%')
            ->select('retencoes.id',
                'retencoes.domain_id',
                'retencoes.valor',
                'retencoes.estado',
                'retencoes.tipo',
                'retencoes.descricao',
                'retencoes.periodicidade',
                'retencoes.parcelas',
                'retencoes.comeca_em as date',
            )
            ->selectRaw('CONCAT(users.nome, " ", users.sobrenome) AS full_name')
            ->orderBy('date', 'desc')
            ->simplePaginate($request->length);
    }

    public function retentions(Request $request, $id)
    {
        return DB::table('parcelas_retencao')
            ->where('retencao_id', $id)
            ->get();
    }
}