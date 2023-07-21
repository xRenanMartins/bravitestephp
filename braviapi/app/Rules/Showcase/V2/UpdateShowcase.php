<?php

namespace App\Rules\Showcase\V2;

use App\Utils\Files;
use Illuminate\Support\Facades\DB;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Category;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Address;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;

class UpdateShowcase
{
    public function execute($payload, $id)
    {
        try {
            DB::beginTransaction();
            $showcase = Showcase::find($id);

            if (!empty($payload["domain_id"])) {
                $showcase->withoutGlobalScope(DomainScope::class);
                $showcase->domain_id = $payload["domain_id"];
            }
            if (!empty($payload["imagem"]) && !empty($payload["imagemName"])) {
                $showcase->imagem = Files::saveFromBase64($payload["imagem"], "showcases/{$id}/item/", $payload["imagemName"]);
            }

            if (!empty($payload["imagem_fundo"]) && !empty($payload["imagemFundoName"])) {
                $showcase->imagem_fundo = Files::saveFromBase64($payload["imagem_fundo"], "showcases/{$id}/background/", $payload["imagemFundoName"]);
            }

            $showcase->ativo = $payload["ativo"];
            $showcase->mostrar_categorias = $payload["mostrar_categorias"];
            $showcase->abrir_loja = $payload["mostrar_loja"];
            $showcase->concierge = $payload["concierge"];
            $showcase->titulo = $payload["titulo"] ?? '';
            $showcase->descricao = $payload["descricao"] ?? '';
            $showcase->ordem = $payload["ordem"] ?? 999;
            $showcase->visualizacao = $payload["visualizacao"];
            $showcase->raio = $payload["raio"];
            $showcase->type = $payload["type"];
            $showcase->link_extern = $payload["link_extern"] == "null" ? null : $payload["link_extern"];
            $showcase->is_office = $payload["is_office"] ?? 0;
            $showcase->identifier = $payload["identifier"] == "null" ? null : $payload["identifier"];
            $showcase->start_date = $payload["start_date"] ?? null;
            $showcase->end_date = $payload["end_date"] ?? null;

            if (isset($payload["vitrine_horario"])) {
                $showcase->horario = $payload["vitrine_horario"];
            }

            $showcase->tipo_concierge = $payload["tipo_concierge"];
            if (isset($payload["concierge"]) && $payload["concierge"] == 1) {
                $e = $showcase->endereco;
            } else {
                $e = new Address();
            }
            if (isset($payload["place_id"])) {
                $e->endereco = $payload["endereco"] ?? 'n/d';
                $e->numero = $payload["numero"] ?? 'n/d';
                $e->bairro = $payload["bairro"] ?? 'n/d';
                $e->cidade = $payload["cidade"] ?? 'n/d';
                $e->cep = isset($payload["cep"]) ? str_replace('-', '', $payload["cep"]) : 'n/d';
                $e->complemento = '';
                $e->latitude = $payload["latitude"] ?? 0;
                $e->longitude = $payload["longitude"] ?? 0;
                $e->place_id = $payload["place_id"];
                $e->save();

                $showcase->endereco_id = $e->id;
            }

            if (isset($payload["lojas"])) {
                if (is_string($payload["lojas"])) {
                    $lojas = Store::whereRaw("id in ({$payload["lojas"]})")->selectRaw("id, imagem, nome")->get();
                } else {
                    $lojas = Store::whereIn("id", $payload["lojas"])->selectRaw("id, imagem, nome")->get();
                }
                $showcase->metadados_lojas = $lojas;
            }

            if ($payload["type_showcase"] == 4) {  // LOCAL OFFICE
                $showcase->is_office = 1;
            } else if ($payload["type_showcase"] == 2) { // LOCAL
                $showcase->is_office = 0;
            } else if ($payload["type_showcase"] == 1) {   // DELIVERY
                $showcase->endereco_id = null;
                $showcase->is_office = 0;
            } else if ($payload["type_showcase"] == 0) { // delivery OFFICE
                $showcase->endereco_id = null;
                $showcase->is_office = 1;
            }

            if (!$showcase->ativo) {
                Category::query()->where('vitrine_id', $showcase->id)->update(['ativo' => 0]);
            }

            $showcase->save();
            DB::commit();
            dispatch(new SendShowcaseFeedEvent($showcase->id, 'showcase.update'));

            return $showcase;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}