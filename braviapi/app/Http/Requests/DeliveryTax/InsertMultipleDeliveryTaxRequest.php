<?php

namespace App\Http\Requests\DeliveryTax;

use Illuminate\Foundation\Http\FormRequest;

class InsertMultipleDeliveryTaxRequest extends FormRequest
{
    public function rules()
    {
        if ($this->hasFile('file')) {
            $validators = ['file' => 'required'];
        } else {
            $validators = ['ids' => 'required|array'];
        }

        return array_merge([
            'expire_in' => 'nullable|date_format:Y-m-d H:i:s',
            'value' => 'required|numeric|min:0',
        ], $validators);
    }
}