<?php

namespace App\Rules\Store;

use Packk\Core\Models\Address;

class CitiesAutocomplete
{
    public function execute($request)
    {
        $query = Address::query()->whereNotNull('cidade')
            ->whereNotNull('loja_id');

        if (!empty($request->name)) {
            $query->where('cidade', 'LIKE', "{$request->name}%");
        }

        return $query->selectRaw("IF(state is not null, CONCAT(cidade, ' - ', state),cidade)  as city")
            ->limit(10)->groupBy(['cidade'])->orderByDesc('id')->get();
    }
}