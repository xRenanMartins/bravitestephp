<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "name" => "required|unique:groups,name",
            "description" => "sometimes",
            "type" => "required",
            "group_settings" => "sometimes",
            "group_id" => "sometimes",
            "fixed" => "sometimes",
            "ids" => "sometimes",
            "categories" => "sometimes",
            "file" => "sometimes",
        ];
    }

    public function messages()
    {
        return [
            "name.unique" => "Já existe um grupo com esse nome",
        ];
    }
}
