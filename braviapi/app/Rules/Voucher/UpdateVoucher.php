<?php

namespace App\Rules\Voucher;

use Packk\Core\Models\Voucher;
use Packk\Core\Models\VoucherProduct;
use Packk\Core\Exceptions\RuleException;
use Carbon\Carbon;

class UpdateVoucher
{
    public function execute($payload)
    {
        $imagem = null;
        $desconto = 0;
        $cash_back = 0;

        try {

            $verifyVoucher = VerifyVoucher::verifyByKey($payload->chave, $payload->voucher_id);
            if (!$verifyVoucher) {
                throw new RuleException(
                    "Ops...",
                    "Já existe um cupom ativo com esta chave, tente outra chave para continuar"
                );
            }

            $payload->regiao = isset($payload->regiao) ? $payload->regiao : null;
            if (isset($payload->regiao) && $payload->regiao == 'todas') {
                $payload->regiao = null;
            }

            $inicio = isset($payload->inicio) ? Carbon::createFromFormat('d/m/Y H:i', $payload->inicio) : null;

            if ($payload->tipo_desconto == 'A' || $payload->tipo_desconto == 'FRETE_FIXO' || $payload->tipo_desconto == "VALOR_MAXIMO") {
                $desconto = $payload->desconto;
            } else if ($payload->tipo_desconto == 'P' || $payload->tipo_desconto == 'FRETE_PERCENTUAL') {
                $desconto = $payload->desconto;
            } else {
                $cash_back = $payload->desconto;
                $desconto = 0;
            }

            $primeira_compra = false;

            if (isset($payload->primeira_compra)) {
                if ($payload->primeira_compra == "Y") {
                    $primeira_compra = true;
                } else if ($payload->primeira_compra == "N") {
                    $primeira_compra = false;
                } else {
                    $primeira_compra = true;
                }
            }

            $frete_gratis = false;

            if (isset($payload->tipo_desconto)) {
                if ($payload->tipo_desconto == "FRETE_GRATIS") {
                    $payload->tipo_desconto = "A";
                    $frete_gratis = true;
                }
            }

            $voucher = Voucher::find($payload->voucher_id);

            $voucher->update([
                'chave' => $payload->chave,
                'desconto' => $desconto,
                'valor_minimo' => $payload->valor_minimo,
                'tipo_desconto' => $payload->tipo_desconto,
                'validade' => Carbon::createFromFormat('d/m/Y H:i', $payload->validade),
                'promotion_recurrence' => $payload->promotion_recurrence ?? null,
                'quantidade_total' => $payload->quantidade_total,
                'quantidade_por_usuario' => $payload->quantidade_por_usuario,
                'frete_gratis' => $frete_gratis,
                'primeira_compra' => $primeira_compra,
                'regiao' => $payload->regiao,
                'cash_back' => $cash_back,
                'limite_valor' => $payload->limite_valor,
                'inicio' => $inicio,
                'tipo_loja' => $payload->tipo_loja ?? "TODAS",
                'imagem' => $imagem,
                'customer_group_id' => isset($payload->customer_group) ? $payload->customer_group : null,
            ]);

            if (isset($payload->veiculacao) && $payload->veiculacao != -1) {
                $voucher->veiculacao = $payload->veiculacao;
                $voucher->save();
            }

            $voucher->products()->delete();
            if (isset($payload->check_products_type) && isset($payload->check_products) && !empty($payload->produtos)) {
                if ($payload->check_products_type == "id") {
                    $voucher->products()->saveMany($this->getArrayProductsIds($payload->produtos));
                } else {
                    $voucher->products()->saveMany($this->getArrayProductsEan($payload->produtos));
                }
            }

            if (isset($payload->check_customer) && !empty($payload->clientes)) {
                $voucher->clientes()->sync(explode(',', str_replace("\n", ',', $payload->clientes)));
            }

            if (isset($payload->check_stores) && strlen($payload->loja_ids) > 0) {
                $voucher->lojas()->sync(explode(',', $payload->loja_ids));
            }

            return ["success" => true];
        } catch (\Throwable $e) {
            throw $e;
        }

    }

    private function getArrayProductsEan($products)
    {
        $remotes = explode(',', str_replace("\n", ',', $products));
        $many = collect([]);
        foreach ($remotes as $remote) {
            $many->push(new VoucherProduct(["ean" => $remote]));
        }
        return $many;
    }

    private function getArrayProductsIds($products)
    {
        $remotes = explode(',', str_replace("\n", ',', $products));
        $many = collect([]);
        foreach ($remotes as $remote) {
            $many->push(new VoucherProduct(["product_id" => $remote]));
        }
        return $many;
    }
}
