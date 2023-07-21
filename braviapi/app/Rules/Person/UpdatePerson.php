<?php

namespace App\Rules\Person;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class UpdatePerson
{
    protected $payload;

    public function execute($payload, $id)
    {
        $this->payload = $payload;
        $person = Person::findOrFail($id);
        $person->name = $this->payload['name'];
        $person->lastname = $this->payload['lastname'];
        $person->save();

        return $person;
    }
}
