<?php

namespace App\Http\Requests\Person;

use Illuminate\Foundation\Http\FormRequest;

class PersonCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'lastname' => 'sometimes',
        ];
    }
}
