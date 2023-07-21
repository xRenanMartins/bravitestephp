<?php

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Models\News;

class StoreNewsRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $city = $this->get('type_store');
        if (empty($city)) {
            $this->merge(['type_store' => [News::TYPE_STORE_PARTNER, News::TYPE_STORE_MARKETPLACE, News::TYPE_STORE_PLACE]]);
        }
        // $activeIn = $this->get('active_in');
        // if (!empty($activeIn)) {
        //     $this->merge(['active_in' => $activeIn . ':00']);
        // }
        // $disableOn = $this->get('disable_on');
        // if (!empty($disableOn)) {
        //     $this->merge(['disable_on' => $disableOn . ':00']);
        // }
    }

    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        $addressee = $this->get('addressee');
        if ($addressee == 'L') {
            $validators = [
                'addressee' => 'required',
                'title' => 'sometimes',
                'message' => $this->type == News::TYPE_ONLY_TEXT ? 'required' : 'sometimes',
                'regions' => 'sometimes',
                'type_content' => 'required',
                'type_store' => 'required|array',
                'type_action' => 'required',
                'preview_image' => 'required',
                'equal_image' => 'sometimes',
                'content_image' => $this->equal_image || $this->get('type', News::TYPE_ONLY_TEXT) == News::TYPE_ONLY_TEXT
                    ? 'sometimes'
                    : 'required',
            ];

            if (in_array($this->type_action, [News::TYPE_ACTION_CUSTOM, News::TYPE_ACTION_MULTIPLE_OPTIONS])) {
                $validators['redirect_url'] = 'required';
                $validators['cta_redirect'] = 'required';

                if ($this->type_action === News::TYPE_ACTION_MULTIPLE_OPTIONS) {
                    $validators['cta_dismiss'] = 'required';
                }
            } else {
                $validators['cta_confirm'] = 'required';
            }

        } else {
            $validators = [
                'addressee' => 'required',
                'title' => 'required',
                'message' => 'required',
                'regions' => 'required',
            ];
        }

        $validators['active_in'] = 'required';
        $validators['disable_on'] = 'required';

        return $validators;
    }
}
