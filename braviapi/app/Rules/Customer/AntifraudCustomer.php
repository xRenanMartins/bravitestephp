<?php

namespace App\Rules\Customer;

use Packk\Core\Integration\Freshdesk\Freshdesk;
use Carbon\Carbon;
use Packk\Core\Models\Store;

class AntifraudCustomer
{
    public function execute($client, $amount, $setAmount = true, $store_id = null)
    {
        try {
            $store = Store::findOrFail($store_id);
            $domain = currentDomain(true);
            if ($domain->getSetting("evaluate_max_value_order_customer_day", false)) {
                $amount_customer_today = $amount + $client->getSetting("amount_customer_today", 0);

                if ($setAmount) {
                    $client->setSetting("amount_customer_today", $amount_customer_today);
                }

                $max_value_order_customer_day = $store->getSetting('max_value_order_customer_day', 65000);
                if ($amount_customer_today >= $max_value_order_customer_day && $max_value_order_customer_day > 0) {
                    $client->user->status = "SUSPEITO";
                    $client->user->save();

                    $this->createTicket($client, $store_id);
                }
            }
        } catch (\Exception$e) {
            throw $e;
        }
    }

    private function createTicket($cliente, $store_id = null)
    {
        $domain = currentDomain(true);
        $store = Store::findOrFail($store_id);
        $freshdesk_credentials = $domain->getSetting('freshdesk_credentials', []);

        $freshDesk = new Freshdesk($domain->id);
        $customFields = [
            'cf_responsvel_pela_resposta' => $domain->getSetting('freshdesk_responsible_to_response', 'Vinicius'),
        ];
        $nivelUrgencia = 4; // Urgente
        $statusTicket = 2; // Aberto
        $groupId = $freshdesk_credentials->group_id ?? 2043001657449;

        $max_value_order_customer_day = number_format($store->getSetting('max_value_order_customer_day', 65000) / 100, 2, ',', '.');

        $today = Carbon::now()->format('Y-m-d H:i');

        $description = "<br><br>";
        $description .= "{$cliente->user->nome} está tentando comprar acima de R$ {$max_value_order_customer_day} no dia de hoje ({$today})<br><br>";

        $description .= "Nome: {$cliente->user->nome} <br>";
        $description .= "Nome Cpf: {$cliente->user->cpf_name} <br>";
        $description .= "Telefone: {$cliente->user->telefone} <br>";
        $description .= "Email: {$cliente->user->email} <br>";
        $description .= "Foto Perfil: {$cliente->user->foto_perfil}";

        $store_name = null;
        if (isset($store_id)) {
            $store = Store::find($store_id);
            $store_name = isset($store->nome) ? "- {$store->nome}" : "";
        }

        $freshDesk->create_ticket(
            $cliente->user->nome,
            $domain->getSetting('email_sender_freshdesk', 'monitoramento@zaitt.com.br'),
            "Tentativa de compra de alto valor {$store_name}",
            $description,
            $statusTicket,
            $nivelUrgencia,
            $groupId,
            $customFields
        );
    }
}
