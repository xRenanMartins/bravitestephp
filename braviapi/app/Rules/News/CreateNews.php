<?php

namespace App\Rules\News;

use App\Traits\DateTrait;
use App\Utils\Files;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\News;

class CreateNews
{
    use DateTrait;
    
    public function execute($payload)
    {
        $news = new News();

        $news->user_id = Auth::id();
        $news->created_at = now();
        $news->updated_at = now();
        $news->destinatario = $payload['addressee'];
        $news->regiao = 'LISTA';
        $news->active_in = self::getFormattedDate($payload['active_in']);
        $news->disable_on = self::getFormattedDate($payload['disable_on']);
        if (!empty($news->active_in) && !empty($news->disable_on) && Carbon::parse($news->active_in)->isAfter(Carbon::parse($news->disable_on))) {
            throw new CustomException('A datas informadas são inválidas. O ínicio deve ser menor que o fim.');
        }

        if ($payload['addressee'] == 'L') {
            $news->type_action = $payload['type_action'];
            $news->type_content = $payload['type_content'];
            $news->redirect_url = $payload['redirect_url'] ?? null;
            $news->cta = [
                'redirect' => $payload['cta_redirect'] ?? null,
                'dismiss' => $payload['cta_dismiss'] ?? null,
                'confirm' => $payload['cta_confirm'] ?? null,
            ];

            $news->type_store = implode(',', $payload['type_store']);
        }

        $news->titulo = $payload['title'] ?? '';
        $news->conteudo = $payload['message'] ?? '';
        $news->save();

        if (!empty($payload['preview_image'])) {
            $news->imagem = Files::save($payload['preview_image'], "news/{$news->id}/", 'preview');

            if (isset($payload['equal_image']) && $payload['equal_image']) {
                $news->content_image = $news->imagem;
            }
        }

        if (!empty($payload['content_image'])) {
            $news->content_image = Files::save($payload['content_image'], "news/{$news->id}/", 'content');
        }

        $news->save();

        if (isset($payload['regions'])) {
            $news->firebase_topics()->attach($payload['regions']);
        }
        return $news;
    }
}