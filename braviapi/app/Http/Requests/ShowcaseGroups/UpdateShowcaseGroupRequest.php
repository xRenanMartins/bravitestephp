<?php

namespace App\Http\Requests\ShowcaseGroups;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShowcaseGroupRequest extends FormRequest
{
    protected function prepareForValidation()
    {
    }

    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        $validators = [
            'title' => 'sometimes',
            'image' => 'sometimes',
            'ordem' => 'sometimes',
            'active' => 'sometimes',
            'showcases' => 'sometimes'
        ];

        return $validators;
    }
}
