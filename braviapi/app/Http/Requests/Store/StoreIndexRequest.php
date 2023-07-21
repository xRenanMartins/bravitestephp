<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreIndexRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $haveFranchise = $this->get('have_franchise', 0);

        $authUser = Auth::user();
        if ($authUser->isFranchiseOperator()) {
            $haveFranchise = 1;

            $franchise = $authUser->getFranchise();
            if (!empty($franchise)) {
                $haveFranchise = 0;
                $this->merge(['franchise_id' => $franchise->id]);
            }
        }

        $this->merge(['have_franchise' => intval($haveFranchise)]);

        $city = $this->get('city');
        if (!empty($city)) {
            $this->merge(['city' => explode(' - ', $city)[0]]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id' => 'sometimes',
            'shopkeeper_id' => 'sometimes',
            'name' => 'sometimes',
            'cnpj' => 'sometimes',
            'habilitated' => 'sometimes',
            'franchise_id' => 'sometimes',
            'city' => 'sometimes',
            'status' => 'sometimes',
            'have_franchise' => 'required|integer',
            'active' => 'sometimes',
        ];
    }
}
