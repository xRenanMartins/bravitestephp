<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Packk\Core\Models\AcceptedAttempt;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\Deliveryman;
use Packk\Core\Models\DeliveryRequest;
use Packk\Core\Models\NotificationRequest;
use Packk\Core\Models\RejectedOrder;
use Packk\Core\Models\Retention;
use Packk\Core\Models\Turno;
use Packk\Core\Models\ShiftStore;
use Packk\Core\Models\DeliveryTurn;
use Packk\Core\Models\Delivery;
use Packk\Core\Models\FirebaseTopic;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\Auth;

class ScaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Turno::identic('id', $request->id)
            ->identic('estado', $request->estado)
            ->identic('firebase_topic_id', $request->regiao);
        if (!empty($request->entregador)) {
            $query = $query->whereExists(function ($query) use ($request) {
                $query->select(DB::raw(1))
                    ->from('entregador_turno')
                    ->whereColumn('entregador_turno.turno_id', 'turnos.id')
                    ->where('entregador_turno.entregador_id', $request->entregador);
            });
        }
        if (!empty($request->pago)) {
            $query = $query->whereExists(function ($query) use ($request) {
                $query->select(DB::raw(1))
                    ->from('entregador_turno')
                    ->join('retencoes', 'retencoes.id', '=', 'entregador_turno.retencao_id')
                    ->whereColumn('entregador_turno.turno_id', 'turnos.id')
                    ->whereNotNull('entregador_turno.retencao_id')
                    ->where('retencoes.tipo', 'RETENCAO')
                    ->whereIn('retencoes.estado', $request->pago == 'S' ? ['PAGO', 'ANDAMENTO'] : ['CANCELADO', 'PENDENTE']);
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->simplePaginate($request->length);

        $response = $data->toArray();
        foreach ($data->items() as $k => $slot) {
            $temp = $slot->toArray();
            $temp['valor'] = $slot->valor / 100;
            $temp['valor_corrida'] = $slot->valorCorrida();
            $temp['id_desc'] = sprintf("%06d", $slot->id);
            $temp['inicio_formatado'] = $slot->inicio_formatado;
            $temp['fim_formatado'] = $slot->fim_formatado;
            $temp['valor_formatado'] = $slot->valor_formatado;
            $temp['valor_corrida_formatado'] = $slot->valorCorridaFormatado();
            $temp['visibility'] = (isset($slot->getSetting('shift_options')->private)) ? $slot->getSetting('shift_options')->private : 0;
            $temp['dis_min'] = (isset($slot->getSetting('shift_options')->dis_min)) ? $slot->getSetting('shift_options')->dis_min : 0;
            $temp['dis_max'] = (isset($slot->getSetting('shift_options')->dis_max)) ? $slot->getSetting('shift_options')->dis_max : 0;
            $temp['distancia'] = (isset($slot->getSetting('shift_options')->dis_min) && isset($slot->getSetting('shift_options')->dis_max)) ? $slot->getSetting('shift_options')->dis_min . ' - ' . $slot->getSetting('shift_options')->dis_max : '--';
            $temp['vagas_desc'] = count($slot->entregadores) . '/' . $slot->vagas;
            $temp['regiao_desc'] = $slot->regiao ?? 'ALL';
            $temp['idade_dispatch'] = (isset($slot->getSetting('shift_options')->idade_dispatch) ? $slot->getSetting('shift_options')->idade_dispatch : '--');
            $temp['idade_disp'] = $temp['idade_dispatch'] . ' min';
            $temp['prioridade_dispatch'] = (isset($slot->getSetting('shift_options')->prioridade_dispatch) && $slot->getSetting('shift_options')->prioridade_dispatch == 1) ? 1 : 0;
            $temp['prioridade_disp'] = $temp['prioridade_dispatch'] ? 'ATIVO' : '--';
            $temp['pedagio'] = (isset($slot->getSetting('shift_options')->pedagio) && ($slot->getSetting('shift_options')->pedagio == 1)) ? 1 : 0;
            $temp['pedagio_desc'] = $temp['pedagio'] ? 'ATIVO' : '--';
            $temp['entregas_realizadas'] = 0;
            $temp['custo_em_entregas'] = 0;
            $temp['custo_em_bonus'] = 0;
            $temp['custo_total'] = 0;
            $temp['custo_por_entrega'] = 0;

            $temp['lojas'] = ShiftStore::where("shift_id", $slot->id)->get();

            $inicio = new Carbon($slot->inicio);
            $fim = new Carbon($slot->fim);
            $temp['datas'] = [
                [
                    'data' => $inicio->format('d/m/Y H:i'),
                    'inicio' => $inicio->format('d/m/Y H:i'),
                    'fim' => $fim->format('d/m/Y H:i')
                ]
            ];

            if ($slot->estado == 'ENCERRADO') {
                $base_entrega = 600;
                $bonus_cedido = 0;

                foreach ($slot->entregadores as $entregador) {
                    $temp['entregas_realizadas'] += Delivery::where('entregador_id', $entregador->id)
                        ->whereBetween('created_at', [$inicio, $fim])
                        ->where('estado', 'C')
                        ->count();

                    $bonus_cedido += ($entregador->pivot->estado == 'CONCLUIDO' ? 1 : 0) * $slot->valor;
                }
                $temp['custo_em_entregas'] = $temp['entregas_realizadas'] * $base_entrega / 100;
                $temp['custo_em_bonus'] = $bonus_cedido / 100;
                $temp['custo_total'] = $temp['custo_em_entregas'] + $temp['custo_em_bonus'];
                $temp['custo_por_entrega'] = $temp['entregas_realizadas'] == 0 ? 0 : $temp['custo_total'] / $temp['entregas_realizadas'];
            }

            $temp['entregadores'] = [];
            foreach ($slot->entregadores as $entregador) {
                $retencao = DeliveryTurn::join('retencoes', 'retencoes.id', '=', 'entregador_turno.retencao_id')
                    ->whereNotNull('entregador_turno.retencao_id')
                    ->where('turno_id', $slot->id)
                    ->where('entregador_turno.entregador_id', $entregador->id)
                    ->where('retencoes.tipo', 'RETENCAO')
                    ->select('retencoes.*')
                    ->first();
                $temp['entregadores'][] = [
                    'id' => $entregador->id,
                    'nome_completo' => isset($entregador->user->nome_completo)?$entregador->user->nome_completo:$entregador->user->nome." ".$entregador->user->sobrenome,
                    'estado' => $entregador->pivot->estado,
                    'retencao' => $retencao->estado ?? "NONE"
                ];
            }

            $temp['custo_em_entregas'] = 'R$' . number_format($temp['custo_em_entregas'], 2, ',', '.');
            $temp['custo_em_bonus'] = 'R$' . number_format($temp['custo_em_bonus'], 2, ',', '.');
            $temp['custo_total'] = 'R$' . number_format($temp['custo_total'], 2, ',', '.');
            $temp['custo_por_entrega'] = 'R$' . number_format($temp['custo_por_entrega'], 2, ',', '.');

            $response['data'][$k] = $temp;
        }

        return response($response);
    }

    public function servicedZone(Request $request)
    {
        $zonasAtendidas = AreaServed::regioesAtendidas();
        return response($zonasAtendidas);
    }

    public function relDeliveryMan($id, $entregador_id)
    {
        $entregador = Deliveryman::find($entregador_id);
        $turno = $entregador->turns()->where('turnos.id', $id)->first();

        $inicio = new Carbon($turno->inicio);
        $fim = new Carbon($turno->fim);

        $entregas_rejeitadas = RejectedOrder::where('entregador_id', $entregador->id)
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $entregas_realizadas = Delivery::join('geologs', 'entregas.accepted_at_geolog_id', '=', 'geologs.id')
            ->where('entregas.entregador_id', $entregador->id)
            ->whereBetween('geologs.created_at', [$inicio, $fim])
            ->where('entregas.estado', 'C')
            ->count();

        $entregas_solicitadas = DeliveryRequest::where('entregador_id', $entregador->id)
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $tentativas_aceite = AcceptedAttempt::whereBetween('created_at', [$inicio, $fim])
            ->where('entregador_id', $entregador->id)
            ->count();

        $pedidos_recebidos = NotificationRequest::whereBetween('created_at', [$inicio, $fim])
            ->where('entregador_id', $entregador->id)
            ->where('estado', 'RECEBEU')
            ->count();

        $pedidos_visualizados = NotificationRequest::whereBetween('created_at', [$inicio, $fim])
            ->where('entregador_id', $entregador->id)
            ->where('estado', 'VISUALIZOU')
            ->count();

        $mensagens = collect([]);
        $relatorio_log = collect([]);
        try {
            $logs = ($entregador->logs($fim))->reverse();
            $relatorio_log->push($inicio);
            foreach ($logs as $log) {
                $exp = explode(';', $log);
                $horario = new Carbon($exp[3]);
                if ($horario->lte($inicio)) {
                    continue;
                }
                if ($horario->gte($fim)) {
                    break;
                }
                $relatorio_log->push($horario);
            }
            $relatorio_log->push($fim);

            $size = count($relatorio_log);
            for ($i = 0; $i < $size - 1; $i++) {
                $diff = $relatorio_log[$i]->diffInSeconds($relatorio_log[$i + 1]);
                if ($diff > 600) {
                    $minutos = intval($diff / 60);
                    $mensagens->push("Ficou offline de {$relatorio_log[$i]->format('H:i')} à {$relatorio_log[$i+1]->format('H:i')} ({$minutos} minutos)");
                }
            }
        } catch (\Exception $e) {
            $mensagens->push('indisponível');
        }
        $bonus = [
            'enabled' => false,
            'pivot' => $turno->pivot,
            'bonus' => []
        ];

        if ($turno->pivot and $turno->pivot->retencao_id != null) {
            $bonus['enabled'] = true;
            $bonus['bonus'] = Retention::find($turno->pivot->retencao_id);
            $bonus['bonus']->valor_formatado = $bonus['bonus']->valor_formatado;
        }

        return response([
            'entregas_rejeitadas' => $entregas_rejeitadas,
            'entregas_realizadas' => $entregas_realizadas,
            'entregas_solicitadas' => $entregas_solicitadas,
            'tentativas_aceite' => $tentativas_aceite,
            'pedidos_recebidos' => $pedidos_recebidos,
            'pedidos_visualizados' => $pedidos_visualizados,
            'mensagens' => $mensagens,
            'bonus' => $bonus,
            'domain_id' => $entregador->domain_id
        ]);
    }

    public function addBonus(Request $request)
    {
        $pivot = collect(DB::select("select id,entregador_id,turno_id,estado from entregador_turno where id = ?", [$request->entregador_turno_id]))->first();
        if ($pivot->estado == 'ANALISE') {
            try {
                DB::transaction(function () use ($pivot) {
                    $entregador = Deliveryman::findOrFail($pivot->entregador_id);
                    $turno = Turno::findOrFail($pivot->turno_id);
                    $bonus = Retention::gera_bonus($turno->valor, $entregador, "#{$turno->id} - Bônus por turno de trabalho", "BONUS_SLOT", $turno);
                    DB::update('update entregador_turno set retencao_id=?, updated_at=?, estado=? where id=?', [
                        $bonus->id,
                        now(),
                        'CONCLUIDO',
                        $pivot->id
                    ]);
                });
                return response(['success' => true, 'message' => 'Bônus concedido']);
            } catch (\Exception $e) {
                return response(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        return response(['success' => false, 'message' => 'Não é possível conceder o bônus']);
    }

    public function delBonus(Request $request)
    {
        $pivot = collect(DB::select("select id,entregador_id,turno_id,estado,retencao_id from entregador_turno where id = ? and domain_id = ?", [$request->entregador_turno_id, $request->domain_id]))->first();
        if ($pivot->retencao_id != null) {
            try {
                DB::transaction(function () use ($pivot) {
                    Retention::destroy($pivot->retencao_id);
                    DB::update('update entregador_turno set updated_at=?, estado=? where id=?', [
                        now(),
                        'ANALISE',
                        $pivot->id
                    ]);
                });
                return response(['success' => true, 'message' => 'Bônus revogado']);
            } catch (\Exception $e) {
                return response(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            return response(['success' => false, 'message' => 'Não é possível revogar o bônus']);
        }
    }

    public function save(Request $request)
    {
        try {
            DB::beginTransaction();
            $messages = [];
            if(is_int($request->regiao)){
                $regions = FirebaseTopic::where('id', $request->regiao)->pluck('description');
            }else{
                $regions[0] = $request->regiao;
                $request->regiao = FirebaseTopic::where('description', $request->regiao)->pluck('id');
                $request->regiao = $request->regiao[0];
            }
            foreach ($request->datas as $key => $data) {
                $turno = Turno::findOrNew($request->id);

                $turno->inicio = $data['inicio'];
                $turno->fim = $data['fim'];
                $turno->valor = $request->valor * 100;
                $turno->firebase_topic_id   = $request->regiao;
                $turno->regiao  = $regions[0];
                $turno->vagas = $request->vagas;
                $turno->increment_bonus = $request->increment_bonus == 1 ? 1 : 0;

                if (!empty($request->entregadores)) {
                    foreach ($request->entregadores as $key => $entregador) {
                        $shift = Turno::where([
                            ['turnos.inicio', '<=', $turno->inicio],
                            ['turnos.fim', '>=', $turno->fim],
                            ['entregador_turno.entregador_id', $entregador],
                            ['turnos.estado', 'PROGRAMADO']
                        ])
                            ->join('entregador_turno', 'entregador_turno.turno_id', 'turnos.id')
                            ->first();

                        if (!empty($shift) && $shift->turno_id != $turno->id) {
                            $messages[] = 'Entregador: ' . $entregador . ' esta em outra escala';
                        }
                    }
                }
                $turno->save();

                DeliveryTurn::where("turno_id", $turno->id)->delete();
                if (!empty($request->entregadores)) {
                    foreach ($request->entregadores as $key => $entregador) {
                        $entregadorShift = new DeliveryTurn();
                        $entregadorShift->entregador_id = $entregador;
                        $entregadorShift->turno_id = $turno->id;
                        $entregadorShift->save();
                    }
                }

                if (!empty($request->lojas)) {
                    ShiftStore::where("shift_id", $turno->id)->delete();
                    foreach ($request->lojas as $loja) {
                        if(isset($loja)){
                            $shiftStore = new ShiftStore();
                            $shiftStore->shift_id = $turno->id;
                            $shiftStore->store_id = $loja;
                            $shiftStore->save();
                        }
                    }
                } else {
                    ShiftStore::where("shift_id", $turno->id)->delete();
                }

                $shiftOptions = [
                    "idade_dispatch" => !empty($request->idade_dispatch) ? $request->idade_dispatch : 0,
                    "prioridade_dispatch" => !empty($request->dispatch) ? 1 : 0,
                    "valor_corrida" => $request->valor_corrida * 100,
                    "dis_min" => $request->dis_min,
                    "dis_max" => $request->dis_max,
                    "pedagio" => !empty($request->pedagio) ? 1 : 0,
                    "private" => !empty($request->visibility) ? 1 : 0,
                    "noStore" => empty($request->lojas) ? 1 : 0
                ];

                $turno->setSetting('shift_options', $shiftOptions);
                $turno->save();
            }
            DB::commit();
            return ['success' => true, 'messages' => $messages];

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function deliveryMan(Request $request, $id)
    {
        if ($request->entregador_id) {
            $turno = Turno::find($id);
            $turno->entregadores()->detach([$request->entregador_id]);
        } else if ($request->entregadores) {
            DeliveryTurn::where("turno_id", $id)->delete();
            if (!empty($request->entregadores)) {
                foreach ($request->entregadores as $entregador) {
                    $entregadorShift = new DeliveryTurn();
                    $entregadorShift->entregador_id = $entregador;
                    $entregadorShift->turno_id = $id;
                    $entregadorShift->save();
                }
            }
        }

        return response(['success' => true]);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            DeliveryTurn::where("turno_id", $id)->delete();
            ShiftStore::where("shift_id", $id)->delete();
            DB::delete('DELETE FROM setting_shifts WHERE shift_id = ?', [$id]);
            Turno::destroy($id);
        });

        return response(['success' => true]);
    }

    public function deliverymen(Request $request)
    {
        try {
            $ids = isset($request->ids) ? explode(',', $request->ids) : false;

            return Deliveryman::select("entregadores.id")
                ->selectRaw('CONCAT(users.nome, " ", users.sobrenome) AS nome_completo')
                ->join('users', 'users.id', '=', 'entregadores.user_id')
                ->when($ids, function ($query, $ids) {
                    return $query->whereNotIn("entregadores.id", $ids);
                })
                ->like(DB::raw('CONCAT(users.nome, " ",users.sobrenome)'), $request->name)
                ->get()
                ->map->makeHidden(["entregasCount"]);

        } catch (\Throwable $th) {
            throw $th;
        }
    }
    public function regions(Request $request){
        return FirebaseTopic::query()->where('type', 'ENTREGADOR')->select('id', 'name as label')->get();
    }
    public function storesToTarget(Request $request)
    {
        $user = Auth::user();
        $query = Store::withoutGlobalScope(DomainScope::class);
        $ids = explode(",",$request->stores_selected);
        return $query->join('lojistas', 'lojistas.id', '=', 'lojas.lojista_id')
        ->join('users', 'users.id', '=', 'lojistas.user_id')
        ->join('enderecos', 'enderecos.loja_id', '=', 'lojas.id')
        ->join('domains', 'domains.id', '=', 'lojas.domain_id')
        ->when(!empty($request->nome), function ($q) use ($request) {
            $q->where('lojas.nome', 'like', "{$request->nome}%");
        })
        ->whereNotIn('lojas.id', $ids)
        ->selectRaw('lojas.id, lojas.nome')
        ->orderByDesc('lojas.id')->get();

    }
    public function storesToSource(Request $request)
    {
        $user = Auth::user();
        $query = Store::withoutGlobalScope(DomainScope::class);
        $ids = explode(",",$request->ids);
        return $query->join('lojistas', 'lojistas.id', '=', 'lojas.lojista_id')
        ->join('users', 'users.id', '=', 'lojistas.user_id')
        ->join('enderecos', 'enderecos.loja_id', '=', 'lojas.id')
        ->join('domains', 'domains.id', '=', 'lojas.domain_id')
        ->when(!empty($request->nome), function ($q) use ($request) {
            $q->where('lojas.nome', 'like', "{$request->nome}%");
        })
        ->whereIn('lojas.id', $ids)
        ->identic('lojas.habilitado', $request->habilitado)
        ->selectRaw('lojas.id, lojas.nome')
        ->orderByDesc('lojas.id')->get();

    }
}