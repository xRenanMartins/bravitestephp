<?php

namespace App\Rules\News;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Domain;
use Packk\Core\Models\FirebaseTopic;
use Packk\Core\Models\News;

class DataNewsEdit
{
    private $response;
    private $news;

    public function execute($id = null)
    {
        $this->response = [];
        $this->edit($id);
        $this->getRegions();
        $this->getDomains();

        $this->response['type_content_options'] = [
            ['value' => News::TYPE_ONLY_TEXT, 'label' => 'Somente texto'],
            ['value' => News::TYPE_ONLY_IMAGE, 'label' => 'Somente imagem'],
            ['value' => News::TYPE_TEXT_AND_IMAGE, 'label' => 'Texto + imagem'],
        ];

        $this->response['type_store_options'] = [
            ['value' => News::TYPE_STORE_PARTNER, 'label' => 'Parceiras'],
            ['value' => News::TYPE_STORE_MARKETPLACE, 'label' => 'Marketplace'],
            ['value' => News::TYPE_STORE_PLACE, 'label' => 'Locais'],
        ];

        $this->response['type_actions_options'] = [
            ['value' => News::TYPE_ACTION_AWARE, 'label' => 'Apenas ciência'],
            ['value' => News::TYPE_ACTION_CUSTOM, 'label' => 'Ação específica'],
            ['value' => News::TYPE_ACTION_MULTIPLE_OPTIONS, 'label' => 'Múltipla escolha'],
        ];
        return $this->response;
    }

    private function getRegions()
    {
        $franchises = FirebaseTopic::query()
            ->join('franchises', 'franchises.firebase_topic_id', 'firebase_topics.id')
            ->where('franchises.active', 0)->pluck('firebase_topics.id');

        $regions = FirebaseTopic::query()->whereNotIn('id', $franchises)
            ->selectRaw('id, IF(type = "FRANQUIA", CONCAT("Franquia ", name), name) as name, type')->get();

        $this->response['regions'] = [
            'customer' => $regions->whereIn('type', ['CLIENTE', 'FRANQUIA'])->values(),
            'shopkeeper' => $regions->whereIn('type', ['LOJISTA', 'FRANQUIA'])->values(),
            'deliveryman' => $regions->where('type', 'ENTREGADOR')->values(),
        ];
    }

    private function getDomains()
    {
        $domains = Cache::remember("store.domains", 86400, function () {
            return Domain::query()->selectRaw('id, title')->get();
        });
        if (!empty($this->news) || !Auth::user()->hasAdminPrivileges()) {
            $domains = $domains->where('id', $this->news->domain_id ?? currentDomain());
        }
        $this->response['domains'] = array_values($domains->toArray());
    }

    private function edit($id)
    {
        if (!empty($id)) {
            $this->news = News::with('firebase_topics')->findOrFail($id);
            $this->response['news'] = [
                'id' => $this->news->id,
                'title' => $this->news->titulo,
                'message' => $this->news->conteudo,
                'active_in' => $this->news->active_in,
                'disable_on' => $this->news->disable_on,
                'created_at' => $this->news->created_at,
                'addressee' => $this->news->destinatario,
                'domain_id' => $this->news->domain_id,
                'franchise_id' => $this->news->franchise_id,
                'type_store' => explode(',', $this->news->type_store),
                'type_content' => $this->news->type_content,
                'type_action' => $this->news->type_action,
                'preview_image' => $this->news->imagem,
                'content_image' => $this->news->content_image,
                'redirect_url' => $this->news->redirect_url,
                'cta' => $this->news->cta,
                'regions' => $this->news->firebase_topics()->pluck('firebase_topic_id')
            ];
        }
    }
}