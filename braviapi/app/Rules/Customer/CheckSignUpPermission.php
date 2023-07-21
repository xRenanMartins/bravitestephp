<?php

namespace App\Rules\Customer;

use Packk\Core\Models\AppIdentifier;

class CheckSignUpPermission
{
    public function execute($identifier)
    {
        $domain = currentDomain(true);

        $users = AppIdentifier::join("users", "users.id", "app_identifiers.user_id")
            ->join("clientes", "users.id", "clientes.user_id")
            ->where("app_identifiers.identifier", $identifier)
            ->where("users.domain_id", $domain->id)
            ->get();

        if ($users->count() >= $domain->getSetting("sign_up_permissions", 3) && $users->where("whitelist", 1)->count() == 0) {
            return false;
        }
        return true;
    }
}
