<?php

namespace App\Rules\Customer;

use Packk\Core\Models\UserActivity;
use Packk\Core\Integration\BigID\People;
use Illuminate\Support\Facades\Auth;

class ConsultCpf
{
    public function execute($user, $authId = null)
    {
        $consult_cpf = UserActivity::join("atividades", "atividades.id", "user_activities.atividade_id")
            ->select("user_activities.context", "user_activities.atividade_id")
            ->where("atividades.flag", "USER_BIG_ID")
            ->where("atividades.domain_id", $user->domain_id)
            ->whereNull("atividades.deleted_at")
            ->where("user_activities.user_id", $user->id)
            ->first();

        if (!isset($consult_cpf)) {
            $bigid = new People(currentDomain(true));
            $payload = collect([]);
            $payload->cpf = $user->cpf;
            $consult_cpf = $bigid->get($payload);

            if (!empty($consult_cpf)) {
                $authId = !empty($authId) ? $authId : Auth::id();
                $user->addAtividade("USER_BIG_ID", ["[::response]" => json_encode($consult_cpf)], $authId, 'ADMIN');
            }
        } else {
            $consult_cpf = json_decode($consult_cpf->context);
        }

        return $consult_cpf;
    }
}
