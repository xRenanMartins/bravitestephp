<?php

namespace App\Rules\Contact;

use App\Models\Contact;

class CreateContact
{
    protected $payload;
    public function execute($payload)
    {
        $this->payload = $payload;
    
        $contact = new Contact();
        $contact->person_id = $this->payload['person_id'];
        $contact->phone = $this->payload['phone'];
        $contact->email = $this->payload['email'];
        $contact->whatsapp = $this->payload['whatsapp'];
        
        $contact->save();
        
        return $contact;
    }
}
