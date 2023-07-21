<?php

namespace App\Rules\Customer;

use Packk\Core\Actions\Admin\Customer\BanCustomer;
use Packk\Core\Models\Customer;
use Packk\Core\Models\AppIdentifier;

class BanCustomersAppIdentifier
{
    public function execute($payload)
    {
        $customer = Customer::findOrFail($payload["cliente_id"]);

        $users = AppIdentifier::select("app_identifiers.user_id", "clientes.id as client_id", "clientes.banido", "users.nome", "users.email", "clientes.domain_id")
            ->join("users", "users.id", "app_identifiers.user_id")
            ->join("clientes", "users.id", "clientes.user_id")
            ->whereRaw("app_identifiers.identifier IN (SELECT identifier FROM app_identifiers WHERE user_id = {$customer->user->id} AND identifier != '' AND identifier IS NOT NULL)")
            ->where("app_identifiers.user_id", "!=", $customer->user->id)
            ->where("users.domain_id", $customer->domain_id)
            ->get();

        foreach ($users as $user) {
            $ban_payload = collect([]);
            $ban_payload->reason = $payload["reason"];
            $ban_payload->cliente_id = $user->client_id;
            $ban_payload->descricao = 'App identifier bloqueado';

            (new BanCustomer)->execute($ban_payload);
        }
    }
}
