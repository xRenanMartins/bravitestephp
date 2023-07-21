<?php

namespace App\Rules\Customer;

use Packk\Core\Models\Reason;
use Packk\Core\Models\AppIdentifier;
use Packk\Core\Integration\Freshdesk\Freshdesk;

class CheckAppIdentifier
{
    public function execute($identifier, $client)
    {
        if ($identifier !== null) {
            $users = AppIdentifier::select("app_identifiers.user_id", "clientes.id as client_id", "clientes.banido", "users.nome", "users.email", "clientes.domain_id",
                "users.cpf_name", "users.telefone", "users.email", "users.foto_perfil")
                ->join("users", "users.id", "app_identifiers.user_id")
                ->join("clientes", "users.id", "clientes.user_id")
                ->where("app_identifiers.identifier", $identifier)
                ->where("clientes.banido", 1)
                ->get();

            if ($users->count() > 0) {
                $ban_reason = Reason::where('domain_id', $client->domain->id)->where([['service_provider', 'ADMIN'], ['tipo', 'ADMIN_CLIENTE']])->first();

                $payload = collect([]);
                $payload->reason = $ban_reason->id;
                $payload->cliente_id = $client->id;
                $payload->descricao = 'App identifier bloqueado';

                (new BanCustomer)->execute($payload);

                if ($client->domain->hasFeature("freshchat_app_identifier")) {
                    $this->createTicket($client->user, $users);
                }
            }
        }
    }

    private function createTicket($user, $banned_users)
    {
        $domain = $user->domain;
        $freshdesk = new Freshdesk($domain->id);
        $prepareCreateTicket = $freshdesk->prepareCreateTicket($user);

        $message = "- Cliente bloqueado atráves de um dispositivo utilizado por outras contas bloqueadas<br><br>";
        $message .= "Novo cadastro bloqueado:";
        $message .= $prepareCreateTicket["description"];
        $message .= "<br><br>Cadastros bloqueados anteriormente no mesmo dispositivo:";

        foreach ($banned_users as $banned_user) {
            $message .= "<br><br>";
            $message .= "Nome: {$banned_user->nome} <br>";
            $message .= "Nome Cpf: {$banned_user->cpf_name} <br>";
            $message .= "Telefone: {$banned_user->telefone} <br>";
            $message .= "Email: {$banned_user->email} <br>";
            $message .= "Foto Perfil: {$banned_user->foto_perfil}";
        }

        $freshdesk_credentials = $domain->getSetting('freshdesk_credentials', []);

        $freshdesk->create_ticket(
            $prepareCreateTicket["name"],
            $domain->getSetting('email_sender_freshdesk', 'monitoramento@zaitt.com.br'),
            "Cadastro Cliente inválido - {$domain->title}",
            ($message),
            2, // statusTicket - Aberto
            4, // nivelUrgencia - Urgente
            isset($freshdesk_credentials->group_id) ? $freshdesk_credentials->group_id : 2043001657449, //groupId
            ['cf_responsvel_pela_resposta' => $domain->getSetting('freshdesk_responsible_to_response', 'Vinicius')]//customFields
        );
    }
}
