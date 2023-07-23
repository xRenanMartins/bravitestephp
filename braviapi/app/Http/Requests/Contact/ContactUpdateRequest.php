<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class ContactUpdateRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'person_id' => 'required',
            'phone' => 'sometimes',
            'email' => 'sometimes',
            'whatsapp' => 'sometimes',
        ];
    }
}
