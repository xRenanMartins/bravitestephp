<?php

namespace App\Rules\PushScheduled;

use Carbon\Carbon;
use Packk\Core\Models\PushScheduled;

class CreatePush
{
    public function execute($title, $message, $audience, $type)
    {
        $pushScheduled = new PushScheduled();
        $pushScheduled->mensagem = $message;
        $pushScheduled->titulo = $title;
        $pushScheduled->estado = "ENVIADO";
        $pushScheduled->aprovado = 1;
        $pushScheduled->horario = Carbon::now()->addHour();

        if ($type == "I") {
            $pushScheduled->audiencia = [
                "user_ids" => $audience,
                "topics" => [],
            ];
        } else {
            $pushScheduled->audiencia = [
                "user_ids" => [],
                "topics" => explode(',', str_replace("\n", ',', $audience)),
            ];
        }
        $pushScheduled->save();
    }
}