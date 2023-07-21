<?php

namespace App\Rules\Person;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class CreatePerson
{
    protected $payload;
    public function execute($payload)
    {
        $this->payload = $payload;
    
        $person = new Person();
        $person->name = $this->payload['name'];
        $person->lastname = $this->payload['lastname'];
        $person->save();

        return $person;
    }
}
