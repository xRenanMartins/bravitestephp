<?php

namespace App\Http\Controllers;

use App\Utils\Files;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\User;
use Packk\Core\Models\FirebaseTopic;
use Packk\Core\Models\PushScheduled;
use Packk\Core\Models\News;
use Packk\Core\Models\CustomerGroup;

class PushNotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = PushScheduled::query();
        $user = Auth::user();
        if ($user->isFranchiseOperator()) {
            $query->join('franchises', 'push_programados.franchise_id', '=', 'franchises.id');
        } else {
            $query->leftJoin('franchises', 'push_programados.franchise_id', '=', 'franchises.id');
        }

        return $query->identic('estado', $request->estado)
            ->identic('aprovado', $request->aprovado)
            ->like('titulo', $request->titulo)
            ->identic('franchise_id', $request->get('franchise_id', $user->getFranchise()->id ?? null))
            ->selectRaw('push_programados.*, franchises.name as franchise_name')
            ->orderByDesc('created_at')
            ->simplePaginate($request->length);
    }

    public function create(Request $request)
    {
        $franchises = FirebaseTopic::join('franchises', 'franchises.firebase_topic_id', 'firebase_topics.id' )
        ->where('franchises.active', 0)->pluck('firebase_topics.id');

        $groupRFM = CustomerGroup::query()->orderBy('title')->get();

        $groupTopicClient = FirebaseTopic::where('type', 'CLIENTE')->orWhere(function($q){
            return $q->where('type', 'FRANQUIA');
        })->whereNotIn('id', $franchises)->selectRaw('id, IF(type = "FRANQUIA", CONCAT(type, " ", name), name) as name')->orderBy('name')->get();
        $groupTopicDeliveryman = FirebaseTopic::where('type', 'ENTREGADOR')->select('id', 'name')->orderBy('name')->get();

        return [
            'rfm' => $groupRFM,
            'topicClient' => $groupTopicClient,
            'topicDeliveryman' => $groupTopicDeliveryman,
        ];
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'titulo' => 'required',
            'mensagem' => 'required',
            'imagem' => 'nullable',
            'groupID' => 'nullable',
            'groupID.*' => 'numeric|min:1',
            'groupRFM' => 'nullable',
            'groupTopic.*' => 'string',
            'groupRFM.*' => 'string',
            'horario' => 'required|date_format:Y-m-d H:i:s',
            'aprovado' => 'required',
            'destinatario' => 'required',
            'novidade' => 'required',
            'topicsDeliveryMan' => 'nullable',
            'topicsClient' => 'nullable',
            'franchise_id' => 'nullable|int'
        ]);

        try {
            DB::beginTransaction();
            if (!empty($request->imagem)) {
                $payload['imagem'] = Files::saveFromBase64($request->imagem, 'push_scheduled/', $request->imagemName);
            }

            if (!empty($payload['groupID'])) {
                $lists = explode(',', str_replace("\n", ',', $payload['groupID']));
                $lists = User::whereIn("id", $lists)->select('id')->get()->pluck(["id"])->map(function ($id) {
                        return strval($id);
                    })->toArray();

                if (empty($lists)) {
                    return ['success' => false, 'message' => 'Nenhum ID válido'];
                }
            }

            if (isset($payload['topicsDeliveryMan'])) {
                $topicsDeliveryMan = $payload['topicsDeliveryMan'];
            }
            if (isset($payload['topicsClient'])) {
                $topicsClient = $payload['topicsClient'];
            }

            if (isset($payload['groupRFM'])) {
                $groups_rfm = explode(',', $payload['groupRFM']);
            }
            $payload['audiencia'] = [
                "user_ids" => $lists ?? null,
                "topics_deliveryMan" => $topicsDeliveryMan ?? null,
                "topics_client" => $topicsClient ?? null,
                "groups_rfm" => $groups_rfm ?? null,
            ];

            if ($payload['novidade']) {
                $regions = $topicsClient ?? $topicsDeliveryMan ?? [];
                $news = News::create([
                    'user_id' => Auth::id(),
                    'franchise_id' => $payload['franchise_id'] ?? null,
                    'titulo' => $payload['titulo'],
                    'conteudo' => $payload['mensagem'],
                    'horario' => $payload['horario'] ?? Carbon::now(),
                    'destinatario' => $payload['destinatario'],
                    'regiao' => count($regions) > 0 ? 'LISTA' : 'TODAS',
                ]);
                $news->firebase_topics()->attach($regions);
            }

            unset($payload['groupID']);
            unset($payload['topicsDeliveryMan']);
            unset($payload['topicsClient']);
            unset($payload['groupRFM']);

            $push_programado = PushScheduled::create($payload);

            if (empty($payload['horario']) and $payload['aprovado'] == '1') {
                $push_programado->enviarPush(true);
            }

            if (isset($news)) {
                $news->update(['push_id' => $push_programado->id]);
            }

            DB::commit();
            return response([
                'success' => true,
                'data' => $push_programado
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
        }
    }

    public function approve($id)
    {
        $push_programado = PushScheduled::find($id);
        $push_programado->aprovado = true;
        $push_programado->save();

        return response([
            'success' => true,
            'data' => $push_programado
        ]);
    }

    public function destroy($id)
    {
        try {
            $push_programado = PushScheduled::findOrFail($id);
            $push_programado->delete();

            if ($push_programado->estado === 'PROGRAMADO') {
                $push_programado->news()->delete();
            }

            return response([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function update($id, Request $request){
        $payload = $request->validate([
            'titulo' => 'required',
            'mensagem' => 'required',
            'horario' => 'required|date_format:Y-m-d H:i:s',
            'aprovado' => 'required',
            'franchise_id' => 'nullable|int',
            'audiencia' => 'nullable'
        ]);
        $push = PushScheduled::where('id', $id)->update($payload);
        return response([
            'success' => true,
            'data' => $push,
        ]);
    }
}