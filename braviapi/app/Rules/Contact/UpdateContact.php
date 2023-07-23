<?php

namespace App\Rules\Contact;

use App\Models\Contact;

class UpdateContact
{
    protected $payload;
    public function execute($payload, $id)
    {
        $this->payload = $payload;

        $contact = Contact::findOrFail($id);
        $contact->person_id = $this->payload['person_id'];
        $contact->phone = $this->payload['phone'];
        $contact->email = $this->payload['email'];
        $contact->whatsapp = $this->payload['whatsapp'];

        $contact->save();

        return $contact;
    }
}
