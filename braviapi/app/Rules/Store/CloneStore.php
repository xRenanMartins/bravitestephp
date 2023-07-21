<?php
/**
 * Created by PhpStorm.
 * User: pedrohenriquelecchibraga
 * Date: 2020-08-03
 * Time: 16:13
 */

namespace App\Rules\Store;

use Packk\Core\Models\Category;
use Packk\Core\Models\Address;
use Packk\Core\Models\Store;
use Packk\Core\Models\Product;
use Packk\Core\Models\Hour;

class CloneStore
{
    public function execute($payload, $storeReflect)
    {
        $loja_original = Store::find($payload->loja_id);
        $loja_nova = new Store;

        $loja_nova->nome = $payload->nome_loja ?: $loja_original->nome;
        $loja_nova->descricao = $loja_original->descricao;
        $loja_nova->corporate_name = $loja_original->corporate_name;
        $loja_nova->lojista_id = $loja_original->lojista_id;
        $loja_nova->ativo = $loja_original->ativo;
        $loja_nova->modo_exibicao = "L";
        $loja_nova->parceiro = $loja_original->parceiro;
        $loja_nova->imagem = $loja_original->imagem;
        $loja_nova->imagem_s3 = $loja_original->imagem_s3;
        $loja_nova->comissao = $loja_original->comissao;
        $loja_nova->modo_funcionamento = $loja_original->modo_funcionamento;
        $loja_nova->habilitado = $loja_original->habilitado;
        $loja_nova->segmento_id = $loja_original->segmento_id;
        $loja_nova->telefone = $loja_original->telefone;
        $loja_nova->fechado_ate = $loja_original->fechado_ate;
        $loja_nova->type = $loja_original->type;
        $loja_nova->tipo_exibicao = $loja_original->tipo_exibicao;
        $loja_nova->viu_mensagem = $loja_original->viu_mensagem;
        $loja_nova->save();

        $loja_nova->setSetting('google_place_id', $payload->place_id);

        // cria endereço
        $e = new Address();
        $e->endereco = isset($payload->place_endereco) ? $payload->place_endereco : 'n/d';
        $e->numero = isset($payload->place_numero) ? $payload->place_numero : 'n/d';
        $e->bairro = isset($payload->place_bairro) ? $payload->place_bairro : 'n/d';
        $e->cidade = isset($payload->place_cidade) ? $payload->place_cidade : 'n/d';
        $e->cep = isset($payload->place_cep) ? str_replace('-', '', $payload->place_cep) : 'n/d';
        $e->complemento = '';
        $e->loja()->associate($loja_nova);
        $e->latitude = isset($payload->place_latitude) ? $payload->place_latitude : 0;
        $e->longitude = isset($payload->place_longitude) ? $payload->place_longitude : 0;
        $e->save();

        if (isset($payload->vitrine)) {
            foreach ($loja_original->categories as $c) {
                $c_nova = new Category();
                $c_nova->nome = $c->nome;
                $c_nova->loja_id = $loja_nova->id;
                $c_nova->save();

                foreach ($c->produtos as $p) {
                    $p_novo = new Product();
                    $p_novo->nome = $p->nome;
                    $p_novo->sku = $p->sku;
                    $p_novo->preco = $p->preco;
                    $p_novo->imagem = $p->imagem;
                    $p_novo->descricao = $p->descricao;
                    $p_novo->peso = $p->peso;
                    $p_novo->estoque = $p->estoque;
                    $p_novo->categoria_id = $c_nova->id;
                    $p_novo->store_id = $loja_nova->id;
                    $p_novo->ativo = $p->ativo;
                    $p_novo->status = $p->status;
                    $p_novo->em_promocao = $p->em_promocao;
                    $p_novo->preco_promocional = $p->preco_promocional;
                    $p_novo->granel = $p->granel;
                    $p_novo->recorrencia_promocao = $p->recorrencia_promocao;
                    $p_novo->imagem_s3 = $p->imagem_s3;
                    $p_novo->save();
                    $storeReflect->execute($p, $p_novo);

                    $p_novo->categories()->syncWithoutDetaching([$c_nova->id => [
                        'created_at' => now(),
                        'updated_at' => now(),
                        "domain_id" => $p_novo->domain_id
                    ]]);
                }
            }
        }

        if (isset($payload->horarios)) {
            foreach ($loja_original->horarios as $h) {
                $h_novo = new Hour();
                $h_novo->dia = $h->dia;
                $h_novo->inicio = $h->inicio;
                $h_novo->fim = $h->fim;
                $h_novo->tipo = $h->tipo;
                $h_novo->loja_id = $loja_nova->id;
                $h_novo->save();
            }
        }
        return 0;
    }
}