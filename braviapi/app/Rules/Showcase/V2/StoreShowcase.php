<?php

namespace App\Rules\Showcase\V2;

use App\Utils\Files;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Address;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Scopes\DomainScope;

class StoreShowcase
{
    public function execute($payload)
    {
        $vitrine = new Showcase();

        $vitrine->ativo = $payload["ativo"];
        $vitrine->abrir_loja = $payload["mostrar_loja"];
        $vitrine->mostrar_categorias = $payload["mostrar_categorias"];
        $vitrine->concierge = $payload["concierge"];
        $vitrine->titulo = $payload["titulo"] == null ? '' : $payload["titulo"];
        $vitrine->descricao = $payload["descricao"] == null ? '' : $payload["descricao"];
        $vitrine->ordem = $payload["ordem"] == null ? 999 : $payload["ordem"];
        $vitrine->visualizacao = $payload["visualizacao"];
        $vitrine->raio = $payload["raio"];
        $vitrine->type = $payload["type"];
        $vitrine->start_date = $payload["start_date"] ?? null;
        $vitrine->end_date = $payload["end_date"] ?? null;
        $vitrine->link_extern = $payload["link_extern"] == "null" ? null : $payload["link_extern"];
        $vitrine->is_office = $payload["is_office"] ?? 0;
        $vitrine->identifier = $payload["identifier"] == "null" ? null : $payload["identifier"];

        $vitrine->tipo_concierge = $payload["tipo_concierge"];


        if (isset($payload["vitrine_horario"])) {
            $vitrine->horario = $payload["vitrine_horario"];
        }

        if (isset($payload["place_id"])) {
            $e = new Address();
            $e->endereco = $payload["endereco"] ?? 'n/d';
            $e->numero = $payload["numero"] ?? 'n/d';
            $e->bairro = $payload["bairro"] ?? 'n/d';
            $e->cidade = $payload["cidade"] ?? 'n/d';
            $e->cep = isset($payload["cep"]) ? str_replace('-', '', $payload["cep"]) : 'n/d';
            $e->complemento = '';
            $e->latitude = $payload["latitude"] ?? 0;
            $e->longitude = $payload["longitude"] ?? 0;
            $e->place_id = $payload["place_id"] ?? "";
            $e->save();

            $vitrine->endereco_id = $e->id;
        }
        if (isset($payload["lojas"])) {
            if (is_string($payload["lojas"])) {
                $lojas = Store::whereRaw("id in ({$payload["lojas"]})")->selectRaw("id, imagem, nome")->get();
            } else {
                $lojas = Store::whereIn("id", $payload["lojas"])->selectRaw("id, imagem, nome")->get();
            }
            $vitrine->metadados_lojas = $lojas;
        }

        if ($payload["type_showcase"] == 4) {  // LOCAL OFFICE
            $vitrine->is_office = 1;
        } else if ($payload["type_showcase"] == 2) { // LOCAL
            $vitrine->is_office = 0;
        } else if ($payload["type_showcase"] == 1) {   // DELIVERY
            $vitrine->endereco_id = null;
            $vitrine->is_office = 0;
        } else if ($payload["type_showcase"] == 0) { // delivery OFFICE
            $vitrine->endereco_id = null;
            $vitrine->is_office = 1;
        }

        $vitrine->save();

        if (!empty($payload["imagem"]) && !empty($payload["imagemName"])) {
            $vitrine->imagem = Files::saveFromBase64($payload["imagem"], "showcases/{$vitrine->id}/item/", $payload["imagemName"]);
        }

        if (!empty($payload["imagem_fundo"]) && !empty($payload["imagemFundoName"])) {
            $vitrine->imagem_fundo = Files::saveFromBase64($payload["imagem_fundo"], "showcases/{$vitrine->id}/background/", $payload["imagemFundoName"]);
        }
        $vitrine->save();
        dispatch(new SendShowcaseFeedEvent($vitrine->id, 'showcase.create'));

        return $vitrine;
    }
}