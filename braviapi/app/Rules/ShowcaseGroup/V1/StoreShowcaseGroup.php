<?php

namespace App\Rules\ShowcaseGroup\V1;

use App\Utils\Files;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Address;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Models\ShowcaseGroup;
use Packk\Core\Scopes\DomainScope;

class StoreShowcaseGroup
{
    public function execute($payload)
    {
        $showcaseGroup = new ShowcaseGroup();

        $showcaseGroup->title = $payload["title"];
        $showcaseGroup->ordem = $payload["ordem"];
        $showcaseGroup->active = $payload["active"];
        
        $showcaseGroup->save();

        if (isset($payload["showcases"])) {
            $showcaseGroup->showcases()->attach($payload["showcases"]);
        }

        if (!empty($payload["imagem"])) {
            $showcaseGroup->imagem = Files::saveFromBase64($payload["imagem"], "showcase-groups/{$showcaseGroup->id}/", "showcasegroup");
        }
        $showcaseGroup->save();

        return $showcaseGroup;
    }
}