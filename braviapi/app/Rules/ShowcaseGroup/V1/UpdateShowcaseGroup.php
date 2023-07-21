<?php

namespace App\Rules\ShowcaseGroup\V1;

use App\Utils\Files;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\ShowcaseGroup;

class UpdateShowcaseGroup
{
    public function execute($payload, $id)
    {
        $showcaseGroup = ShowcaseGroup::find($id);

        if (!empty($payload["imagem"])) {
            $showcaseGroup->imagem = Files::saveFromBase64($payload["imagem"], "showcase-groups/{$id}/", "showcasegroup");
        }

        $showcaseGroup->title = $payload["title"];
        $showcaseGroup->ordem = $payload["ordem"];
        $showcaseGroup->active = $payload["active"];

        if (isset($payload["showcases"])) {
            $showcaseGroup->showcases()->sync($payload["showcases"]);
        }

        $showcaseGroup->save();

        return $showcaseGroup;
    }
}