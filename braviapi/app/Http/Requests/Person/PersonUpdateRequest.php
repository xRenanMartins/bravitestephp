<?php

namespace App\Http\Requests\Person;

use Illuminate\Foundation\Http\FormRequest;

class PersonUpdateRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes',
            'lastname' => 'sometimes',
        ];
    }
}
