<?php

namespace App\Rules\Showcase;

use App\Utils\Files;
use Packk\Core\Models\Address;
use Packk\Core\Models\Store;
use Packk\Core\Models\Showcase;
use Illuminate\Support\Facades\Storage;
use Packk\Core\Scopes\DomainScope;

class UpdateShowcase
{
    public function execute($payload)
    {
        $vitrine = new Showcase();
        $vitrine = $vitrine->get_id($payload->id);
        if (!empty($payload->domain_id)) {
            $vitrine->withoutGlobalScope(DomainScope::class);
            $vitrine->domain_id = $payload->domain_id;
        }
        if (!empty($payload->imagem)) {
            $uname = time() . rand() . '.' . $payload->imagem->getClientOriginalExtension();
            $importDir = base_path('media/showcases/item/');
            $payload->imagem->move($importDir, $uname);
            $imagem = \Image::make(base_path('media/showcases/item/' . $uname));
            Storage::put("showcases/{$payload->id}/item/{$uname}", $imagem->stream()->__toString());
            $vitrine->imagem = Storage::url("showcases/{$payload->id}/item/{$uname}");

        }
        if (!empty($payload->imagem_fundo)) {
            if (!empty($payload->imagemFundoName)) {
                $vitrine->imagem_fundo = Files::saveFromBase64($payload->imagem_fundo, "showcases/{$payload->id}/background/", $payload->imagemFundoName);
            } else {
                $uname = time() . rand() . '.' . $payload->imagem_fundo->getClientOriginalExtension();
                $importDir = base_path('media/showcases/fundo/');
                $payload->imagem_fundo->move($importDir, $uname);
                $imagem = \Image::make(base_path('media/showcases/fundo/' . $uname));
                Storage::put("showcases/{$payload->id}/background/{$uname}", $imagem->stream()->__toString());
                $vitrine->imagem_fundo = Storage::url("showcases/{$payload->id}/background/{$uname}");
            }
        }

        $vitrine->ativo = $payload->ativo;
        $vitrine->mostrar_categorias = $payload->mostrar_categorias;
        $vitrine->abrir_loja = $payload->mostrar_loja;
        $vitrine->concierge = $payload->concierge;
        $vitrine->titulo = isset($payload->titulo) ? $payload->titulo : '';
        $vitrine->descricao = isset($payload->descricao) ? $payload->descricao : '';
        $vitrine->ordem = isset($payload->ordem) ? $payload->ordem : 999;
        $vitrine->visualizacao = $payload->visualizacao;
        $vitrine->raio = $payload->raio;
        $vitrine->is_office = isset($payload->is_office) ? $payload->is_office : 0;
        $vitrine->identifier = $payload->identifier == "null" ? null : $payload->identifier;

        if (isset($payload->vitrine_horario)) {
            $vitrine->horario = $payload->vitrine_horario;
        }

        $vitrine->tipo_concierge = isset($payload->tipo_concierge) ? $payload->tipo_concierge : "NONE";
        if (isset($payload->concierge) && $payload->concierge == 1) {
            $e = $vitrine->endereco;
        } else {
            $e = new Address();
        }
        if (isset($payload->place_id)) {
            $e->endereco = isset($payload->endereco) ? $payload->endereco : 'n/d';
            $e->numero = isset($payload->numero) ? $payload->numero : 'n/d';
            $e->bairro = isset($payload->bairro) ? $payload->bairro : 'n/d';
            $e->cidade = isset($payload->cidade) ? $payload->cidade : 'n/d';
            $e->cep = isset($payload->cep) ? str_replace('-', '', $payload->cep) : 'n/d';
            $e->complemento = '';
            $e->latitude = isset($payload->latitude) ? $payload->latitude : 0;
            $e->longitude = isset($payload->longitude) ? $payload->longitude : 0;
            $e->place_id = isset($payload->place_id) ? $payload->place_id : "";
            $e->save();

            $vitrine->endereco_id = $e->id;
        }
        if (isset($payload->lojas)) {
            if (is_string($payload->lojas)) {
                $lojas = Store::whereRaw("id in ({$payload->lojas})")->selectRaw("id, imagem, nome")->get();
            } else {
                $lojas = Store::whereIn("id", $payload->lojas)->selectRaw("id, imagem, nome")->get();
            }
            $vitrine->metadados_lojas = $lojas;
        }

        if ($payload->type_showcase == 3) {
            $vitrine->is_office = 1;
        } else if ($payload->type_showcase == 4) {
            $vitrine->is_office = 1;
        } else if ($payload->type_showcase == 2) {
            $vitrine->is_office = 0;
        } else if ($payload->type_showcase == 1) {
            $vitrine->is_office = 0;
        } else if ($payload->type_showcase == 0) {
            $vitrine->endereco_id = null;
            $vitrine->is_office = 1;
        }

        $validator = $vitrine->save();

        return $validator;
    }
}