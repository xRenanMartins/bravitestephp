<?php

namespace App\Rules\News;

use App\Traits\DateTrait;
use App\Utils\Files;
use Carbon\Carbon;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\News;

class UpdateNews
{
    use DateTrait;

    private $news;
    public function execute($id, $payload)
    {
        $this->news = News::findOrFail($id);

        if (isset($payload['addressee'])) {
            $this->news->destinatario = $payload['addressee'];
        }
        if (isset($payload['active_in'])) {
            $this->news->active_in = self::getFormattedDate($payload['active_in']);
        }
        if (isset($payload['disable_on'])) {
            $this->news->disable_on = self::getFormattedDate($payload['disable_on']);
        }
        if (!empty($this->news->active_in) && !empty($this->news->disable_on) && Carbon::parse($this->news->active_in)->isAfter(Carbon::parse($this->news->disable_on))) {
            throw new CustomException('A datas informadas são inválidas. O ínicio deve ser menor que o fim.');
        }
        if (isset($payload['type_action'])) {
            $this->news->type_action = $payload['type_action'];
        }
        if (isset($payload['type_content'])) {
            $this->news->type_content = $payload['type_content'];
        }
        if (isset($payload['redirect_url'])) {
            $this->news->redirect_url = $payload['redirect_url'];
        }
        if (isset($payload['type_store'])) {
            $this->news->type_store = implode(',', $payload['type_store']);
        }
        if (isset($payload['title'])) {
            $this->news->titulo = $payload['title'];
        }
        if (isset($payload['message'])) {
            $this->news->conteudo = $payload['message'];
        }
        if (isset($payload['preview_image'])) {
            $this->news->imagem = Files::save($payload['preview_image'], "news/{$this->news->id}/", 'preview');
        }

        if (isset($payload['equal_image']) && $payload['equal_image']) {
            $this->news->content_image = $this->news->imagem;
        } else if (isset($payload['content_image'])) {
            $this->news->content_image = Files::save($payload['content_image'], "news/{$this->news->id}/", 'content');
        }

        if (isset($payload['cta_redirect']) || isset($payload['cta_dismiss']) || isset($payload['cta_confirm'])) {
            $this->news->cta = [
                'redirect' => $payload['cta_redirect'] ?? $this->news->cta->redirect ?? null,
                'dismiss' => $payload['cta_dismiss'] ?? $this->news->cta->dismiss ?? null,
                'confirm' => $payload['cta_confirm'] ?? $this->news->cta->confirm ?? null,
            ];
        }

        if (isset($payload['regions'])) {
            $this->news->firebase_topics()->sync($payload['regions']);
        }

        $this->log();
        $this->news->save();
        return $this->news;
    }

    private function log(): void
    {
        try {
            $previous = [];
            foreach ($this->news->getDirty() as $key => $value) {
                $previous[$key] = $this->news->getOriginal($key);
            }
            LogTable::log('UPDATE', "novidades", $this->news->id, 'many', json_encode($previous), json_encode($this->news->getDirty()));
        } catch (\Exception $ex) {
            app('sentry')->captureException($ex);
        }
    }
}