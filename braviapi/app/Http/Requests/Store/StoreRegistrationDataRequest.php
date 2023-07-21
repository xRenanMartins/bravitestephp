<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationDataRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "responsible_name" => "required",
            "last_name" => "required",
            "domain_id" => "required",
            "franchise_id" => "sometimes",
            "login_mail" => "email|required",
            "password" => "required",
            "phone" => "required",
            "responsible_email" => "email|required",
            "store_name" => "required",
            "responsible_phone" => "required",
            "cnpj" => "required",
            "type_store" => "required",
            "corporate_name" => "required",
            "commission" => "required",
            "categories" => "required",
            "is_test" => "required",
            "is_market" => "required",
            "store_slug" => $this->request->get('is_market') ? 'required' : "sometimes",
            "network_slug.id" => $this->request->get('is_market') ? 'required' : "sometimes",
            "network_slug.slug" => $this->request->get('is_market') ? 'required' : "sometimes",
            "offline_commission" => "sometimes",
            "external_link" => "sometimes",
            "shopkeeper_id" => "sometimes",
            "address" => "required",
            "number" => "nullable",
            "district" => "required",
            "city" => "required",
            "postal_code" => "required",
            "complement" => "nullable",
            "state" => "required",
            "latitude" => "nullable",
            "longitude" => "nullable",
            "image" => "nullable",
            "logo" => "nullable",
        ];
    }
    public function messages()
    {
        return [
          'store_slug.required' => 'É obrigatório informar o slug da loja',
          'network_slug.*.required' => 'É obrigatório selecionar uma opção de slug de rede',
        ];
    }
}
