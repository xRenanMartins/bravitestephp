<?php

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsRequest extends FormRequest
{
    // protected function prepareForValidation()
    // {
    //     $activeIn = $this->get('active_in');
    //     if (!empty($activeIn)) {
    //         $this->merge(['active_in' => $activeIn . ':00']);
    //     }
    //     $disableOn = $this->get('disable_on');
    //     if (!empty($disableOn)) {
    //         $this->merge(['disable_on' => $disableOn . ':00']);
    //     }
    // }

    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'addressee' => 'sometimes',
            'title' => 'sometimes',
            'message' => 'sometimes',
            'regions' => 'sometimes',
            'type_content' => 'sometimes',
            'type_store' => 'sometimes',
            'type_action' => 'sometimes',
            'preview_image' => 'sometimes',
            'equal_image' => 'sometimes',
            'content_image' => 'sometimes',
            'redirect_url' => 'sometimes',
            'cta_redirect' => 'sometimes',
            'cta_dismiss' => 'sometimes',
            'cta_confirm' => 'sometimes',
            'active_in' => 'sometimes',
            'disable_on' => 'sometimes',
        ];
    }
}
