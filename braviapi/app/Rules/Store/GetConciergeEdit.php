<?php

namespace App\Rules\Store;

use Packk\Core\Integration\Payment\BankAccount;
use Packk\Core\Integration\Payment\Seller;
use Packk\Core\Models\Store;

class GetConciergeEdit
{
    public function execute($id)
    {
        $loja = Store::find($id);
        $endereco = $loja->enderecos->first();
        if (isset($loja->zoop_seller_id)) {
            $seller = new Seller($loja->zoop_seller_id, true);
            $ba = new BankAccount($seller->default_credit, true);
        }
        $payload = [
            'mcc' => '',
            'nome_lojista' => $loja->lojista->user->nome,
            'sobrenome_lojista' => $loja->lojista->user->sobrenome,
            'cpf' => $loja->lojista->user->cpf,
            'email' => $loja->lojista->user->email,
            'senha' => $loja->lojista->user->password,
            'telefone_lojista' => $loja->lojista->user->telefone,
            'foto_perfil' => $loja->lojista->user->foto_perfil,
            'piso_taxa_servico' => $loja->minimum_service_fee,
            'teto_taxa_servico' => $loja->highest_service_fee,
            'percentual_taxa_servico' => $loja->percentage_service_fee,
            'loja_id' => $loja->id,
            'domain_id' => $loja->domain_id,
            'nome_loja' => $loja->nome,
            'tag_marca' => $loja->tag_brand,
            'ordem' => $loja->ordem,
            'imagem_loja' => $loja->imagem,
            'descricao' => $loja->descricao,
            'modo_exibicao' => $loja->modo_exibicao,
            'categoria_loja' => (count($loja->categories_store) > 0) ? $loja->categories_store->first()->id : '',
            'segmento_loja' => ($loja->segment) ? $loja->segment->id : '',
            'cnpj' => $loja->cnpj,
            'telefone_loja' => $loja->telefone,
            'tipo_exibicao' => $loja->tipo_exibicao,
            'parceiro' => $loja->parceiro,
            'comissao' => $loja->comissao,
            'faixa_etaria' => $loja->age_range,
            'correntista' => isset($ba) ? $ba->holder_name : '',
            'codigo_banco' => isset($ba) ? $ba->bank_code : '',
            'agencia' => isset($ba) ? $ba->routing_number : '',
            'numero_banco' => isset($ba) ? $ba->account_number : '',
            'endereco_loja' => isset($endereco) ? $endereco->endereco : '',
            'numero_loja' => isset($endereco) ? $endereco->numero : '',
            'bairro_loja' => isset($endereco) ? $endereco->bairro : '',
            'cidade_loja' => isset($endereco) ? $endereco->cidade : '',
            'cep_loja' => isset($endereco) ? $endereco->cep : '',
            'complemento_loja' => isset($endereco) ? $endereco->complemento : '',
            'latitude_loja' => isset($endereco) ? $endereco->latitude : '',
            'longitude_loja' => isset($endereco) ? $endereco->longitude : '',
        ];

        if (isset($loja->customer_indicador)) {
            $payload['codigo_indicacao'] = $loja->customer_indicador->codigo_indicacao;
        }

        return $payload;
    }
}