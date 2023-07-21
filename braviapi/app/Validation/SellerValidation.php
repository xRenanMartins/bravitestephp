<?php

namespace App\Validation;

class SellerValidation
{
    public static function storeRules(){
        return [
            "account_number"                => "required",
            "bank_code"                     => "required",
            "business_address_city"         => "required",
            "business_address_country_code" => "required",
            "business_address_line1"        => "required",
            "business_address_line2"        => "required",
            "business_address_line3"        => "sometimes",
            "business_address_neighborhood" => "required",
            "business_address_postal_code"  => "required",
            "business_address_state"        => "required",
            "business_description"          => "sometimes",
            "business_name"                 => "sometimes",
            "business_opening_date"         => "sometimes",
            "ein"                           => "required",
            "holder_name"                   => "required",
            "mcc"                           => "required",
            "owner_birthdate"               => "sometimes",
            "owner_email"                   => "required",
            "owner_first_name"              => "sometimes",
            "owner_last_name"               => "sometimes",
            "owner_phone_number"            => "sometimes",
            "routing_number"                => "required",
            "type"                          => "required",
            "store_id"                      => "sometimes",
            "franchise_id"                  => "sometimes",
            "cartao_cnpj"                   => "sometimes",
            "documento_identificacao"       => "sometimes",
            "comprovante_residencia"        => "sometimes",
            "comprovante_atividade"         => "sometimes",
        ];
    }

    public static function updateRules(){
        return [
            "account_number"                => "required",
            "bank_code"                     => "required",
            "business_address_city"         => "required",
            "business_address_country_code" => "required",
            "business_address_line1"        => "required",
            "business_address_line2"        => "required",
            "business_address_line3"        => "sometimes",
            "business_address_neighborhood" => "required",
            "business_address_postal_code"  => "required",
            "business_address_state"        => "required",
            "business_description"          => "sometimes",
            "business_name"                 => "sometimes",
            "business_opening_date"         => "sometimes",
            "ein"                           => "required",
            "holder_name"                   => "required",
            "mcc"                           => "required",
            "owner_birthdate"               => "sometimes",
            "owner_email"                   => "required",
            "owner_first_name"              => "sometimes",
            "owner_last_name"               => "sometimes",
            "owner_phone_number"            => "sometimes",
            "routing_number"                => "required",
            "type"                          => "required",
            "store_id"                      => "sometimes",
            "franchise_id"                  => "sometimes",
            "seller_id"                     => "required",
            "cartao_cnpj"                   => "sometimes",
            "documento_identificacao"       => "sometimes",
            "comprovante_residencia"        => "sometimes",
            "comprovante_atividade"         => "sometimes",
        ];
    }
}