<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-07-31
 * Time: 18:18
 */

namespace App\Rules\Concierge;

use Packk\Core\Models\Shopkeeper;
use Packk\Core\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateConcierge
{
    public function execute($payload)
    {
        try {

            DB::beginTransaction();
            // carregar lojistaconcierge
            $shopkeeper = Shopkeeper::get_lojista_concierge();

            $store = Store::find($payload->loja_id);
            $store->nome = $payload->nomeloja;
            $store->descricao = $payload->descricao;
            $store->shopkeeper()->associate($shopkeeper);
            //            $store->ativo = true;
            $store->modo_funcionamento = 'A';
            //            $store->parceiro = true;
            $store->ordem = $payload->ordem;
            $store->tipo_exibicao = $payload->tipo_exibicao;
            $store->modo_exibicao = isset($payload->modo_exibicao) ? $payload->modo_exibicao : "L";

            if (isset($payload->imagemlojaConcierge)) {
                $uname = time() . rand() . '.' . $payload->imagemlojaConcierge->getClientOriginalExtension();
                $store->imagem = $uname;

                $importDir = base_path('media/loja2/');
                $payload->imagemlojaConcierge->move($importDir, $uname);

                $imagem = \Image::make(base_path('media/loja2/' . $uname))
                    ->resize(400, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });

                Storage::put("stores/{$store->id}/$uname", $imagem->stream()->__toString());
                $store->imagem_s3 = Storage::url("stores/{$store->id}/$uname");
            }
            $store->save();
            $store->setSetting('age_range', isset($payload->faixa_etaria) ? $payload->faixa_etaria : 0);
            $store->setSetting('google_place_id', $payload->place_id);
            $store->setSetting('percentage_service_fee', $payload->percentual_taxa_servico ?? 0);
            $store->setSetting('minimum_service_fee', $payload->piso_taxa_servico ?? 0);
            $store->setSetting('highest_service_fee', $payload->teto_taxa_servico ?? 0);
            $store->setSetting('tag_brand', isset($payload->tag_marca) ? $payload->tag_marca : null);

            // cria endereço
            $address = $store->enderecos->first();
            $address->endereco = isset($payload->place_endereco) ? $payload->place_endereco : 'n/d';
            $address->numero = isset($payload->place_numero) ? $payload->place_numero : 'n/d';
            $address->bairro = isset($payload->place_bairro) ? $payload->place_bairro : 'n/d';
            $address->cidade = isset($payload->place_cidade) ? $payload->place_cidade : 'n/d';
            $address->cep = isset($payload->place_cep) ? str_replace('-', '', $payload->place_cep) : 'n/d';
            $address->complemento = '';
            $address->loja()->associate($store);
            $address->latitude = isset($payload->place_latitude) ? $payload->place_latitude : 0;
            $address->longitude = isset($payload->place_longitude) ? $payload->place_longitude : 0;
            $address->save();

            DB::commit();
            return ['message' => 'Loja Editada com Sucesso'];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw new \Exception('Ocorreu um erro inesperado no servidor!', 0, $exception);
        }
    }
}