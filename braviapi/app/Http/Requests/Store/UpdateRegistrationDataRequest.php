<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationDataRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "responsible_name" => "sometimes",
            "last_name" => "sometimes",
            "domain_id" => "sometimes",
            "login_mail" => "email|sometimes",
            "password" => "sometimes",
            "phone" => "sometimes",
            "responsible_email" => "email|sometimes",
            "store_name" => "sometimes",
            "responsible_phone" => "sometimes",
            "external_link" => "sometimes",
            "cnpj" => "sometimes",
            "type_store" => "sometimes",
            "corporate_name" => "sometimes",
            "commission" => "sometimes",
            "new_categories" => "sometimes",
            "remove_categories" => "sometimes",
            "is_test" => "sometimes",
            "offline_commission" => "sometimes",
            "address" => "sometimes",
            "number" => "sometimes",
            "district" => "sometimes",
            "city" => "sometimes",
            "postal_code" => "sometimes",
            "complement" => "sometimes",
            "state" => "sometimes",
            "latitude" => "sometimes",
            "longitude" => "sometimes",
            "is_market" => "sometimes",
            "store_slug" => "sometimes",
            "network_slug.id" => $this->request->has('network_slug') ? 'required' : "sometimes",
            "network_slug.slug" => $this->request->has('network_slug') ? 'required' : "sometimes",
            "image" => "sometimes",
            "logo" => "sometimes",
        ];
    }

    public function messages()
    {
        return [
            'network_slug.*.required' => 'É obrigatório selecionar uma opção de slug de rede',
        ];
    }
}
