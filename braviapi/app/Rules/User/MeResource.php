<?php

namespace App\Rules\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->nome,
            "full_name" => $this->nome_completo,
            "email" => $this->email,
            "roles" => $this->dbRoles->pluck('name')
        ];
    }
}