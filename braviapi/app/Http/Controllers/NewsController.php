<?php

namespace App\Http\Controllers;

use App\Utils\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Packk\Core\Models\FirebaseTopic;
use Packk\Core\Models\News;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = News::query();

        $user = Auth::user();
        if ($user->isFranchiseOperator()) {
            $query->join('franchises', 'novidades.franchise_id', '=', 'franchises.id');
        } else {
            $query->leftJoin('franchises', 'novidades.franchise_id', '=', 'franchises.id');
        }

        $query->identic('destinatario', $request->destinatario)
            ->orderByDesc('created_at')
            ->with('firebase_topics')
            ->identic('novidades.id', $request->id)
            ->identic('type_content', $request->type_content);

        if (!empty($request->type_store)) {
            $types = explode(',', $request->type_store);
            $query->where(function($q) use ($types) {
                $q->where('type_store', 'like', "%{$types[0]}%");

                foreach ($types as $key => $type) {
                    if ($key > 0) {
                        $q->orWhere('type_store', 'like', "%{$type}%");
                    }
                }
            });
        }

        if (!empty($request->status)) {
            switch ($request->status) {
                case 'ACTIVE':
                    $query->where('status', 'ACTIVE')
                        ->where(function ($q) {
                            $q->whereNull('active_in')->orWhere('active_in', '<=', now());
                        })->where(function ($q) {
                            $q->whereNull('disable_on')->orWhere('disable_on', '>=', now());
                        });
                    break;
                case 'EXPIRED':
                    $query->whereNotNull('disable_on')->where('disable_on', '<=', now());
                    break;
                case 'PROGRAMMED':
                    $query->whereNotNull('active_in')->where('active_in', '>=', now())
                        ->where('status', 'ACTIVE');
                    break;
                case 'PAUSED':
                    $query->where('status', 'PAUSED')->where(function ($q) {
                        $q->whereNull('disable_on')->orWhere('disable_on', '>=', now());
                    });
                    break;
            }
        }

        return $query->identic('franchise_id', $request->get('franchise_id', $user->getFranchise()->id ?? null))
            ->when($request->filled('regiao'), function ($query) use ($request) {
                $query->whereHas('firebase_topics', function ($query) use ($request) {
                    $query->where('firebase_topic_id', $request->regiao);
                });
            })->selectRaw('novidades.*, franchises.name as franchise_name')
            ->simplePaginate($request->length);
    }

    public function regioes(Request $request)
    {
        $franchises = FirebaseTopic::join('franchises', 'franchises.firebase_topic_id', 'firebase_topics.id')
            ->where('franchises.active', 0)->pluck('firebase_topics.id');
        return [
            'cliente' => FirebaseTopic::where('type', 'CLIENTE')->orWhere(function ($q) {
                return $q->where('type', 'FRANQUIA');
            })->whereNotIn('id', $franchises)->select('id', 'name')->get(),
            'entregador' => FirebaseTopic::where('type', 'ENTREGADOR')->select('id', 'name')->get(),
            'lojista' => FirebaseTopic::where('type', 'LOJISTA')->selectRaw('id, IF(type = "FRANQUIA", CONCAT(type, " ", name), name) as name')->get(),
        ];
    }

    public function store(Request $request)
    {
        $NoShopkeeper = $request->destinatario != "L";
        if ($NoShopkeeper) {
            $payload = $request->validate([
                'titulo' => 'required',
                'conteudo' => 'required',
                'regioes' => 'required|array',
                'destinatario' => 'required',
                'franchise_id' => 'nullable|int',
            ]);
        } else {
            $payload = $request->validate([
                'titulo' => 'required',
                'conteudo' => 'required',
                'destinatario' => 'required',
            ]);
        }

        if (!empty($request->imagem)) {
            $payload['imagem'] = Files::saveFromBase64($request->imagem, 'news/', $request->imagemName);
        }

        $payload['user_id'] = Auth::id();
        $payload['horario'] = Carbon::now();
        if ($NoShopkeeper) {
            $payload['regiao'] = count($payload['regioes']) > 0 ? 'LISTA' : 'TODAS';
            $regions = $payload['regioes'];
            unset($payload['regioes']);
        } else {
            $payload['regiao'] = 'TODAS';
        }

        $news = News::create($payload);
        if ($NoShopkeeper) {
            $news->firebase_topics()->attach($regions);
        }

        return response([
            'success' => true,
            'data' => $news
        ]);
    }

    public function update(Request $request, $id)
    {
        $NoShopkeeper = $request->destinatario != "L";
        if ($NoShopkeeper) {
            $payload = $request->validate([
                'titulo' => 'required',
                'conteudo' => 'required',
                'regioes' => 'required|array',
                'destinatario' => 'required',
                'franchise_id' => 'nullable|int',
            ]);
        } else {
            $payload = $request->validate([
                'titulo' => 'required',
                'conteudo' => 'required',
                'destinatario' => 'required',
            ]);
        }

        if (!empty($request->imagem)) {
            if (!empty($news->imagem)) {
                Storage::delete($news->imagem);
            }
            $payload['imagem'] = Files::saveFromBase64($request->imagem, 'news/', $request->imagemName);
        }

        $news = News::find($id);
        if ($NoShopkeeper) {
            $payload['regiao'] = count($payload['regioes']) > 0 ? 'LISTA' : 'TODAS';
            $news->firebase_topics()->sync($payload['regioes']);
        } else {
            $payload['regiao'] = 'TODAS';
        }

        unset($payload['regioes']);
        $news->update($payload);

        return response([
            'success' => true,
            'data' => $news
        ]);
    }

    public function destroy($id)
    {
        $news = News::find($id);
        try {
            if (!empty($news->imagem)) {
                Storage::delete($news->imagem);
            }
        } catch (\Exception) {
        }

        $news->delete();
        return response([
            'success' => true,
        ]);
    }
}