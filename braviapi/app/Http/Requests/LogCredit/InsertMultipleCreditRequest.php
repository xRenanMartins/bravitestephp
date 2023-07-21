<?php

namespace App\Http\Requests\LogCredit;

use Illuminate\Foundation\Http\FormRequest;

class InsertMultipleCreditRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        if ($this->hasFile('file')) {
            $customers = ['file' => 'required']; // |mimes:csv,xlsx,xls,application/csv,application/excel
        } else {
            $customers = ['customers' => 'required|array'];
        }

        return array_merge([
            'expire_in' => 'nullable|date_format:Y-m-d H:i:s',
            'value' => 'required|numeric|min:.1',
            'reason' => 'nullable|string'
        ], $customers);
    }
}
