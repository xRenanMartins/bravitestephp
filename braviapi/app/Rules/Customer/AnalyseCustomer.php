<?php

namespace App\Rules\Customer;

class AnalyseCustomer
{
    public function execute($payload)
    {
        try {
            $payload->cliente->user->status = 'EM_ANALISE';
            $payload->cliente->user->save();

            $message = ['[::text]' => "Cliente: {$payload->cliente->id} - {$payload->cliente->user->nome_completo} - {$payload->cliente->user->email}, cadastro suspeito pelo motivo:<br> {$payload->motivo[0]}"];
            $payload->cliente->user->addAtividade('SUSPECT_USER', $message);
            return $payload->cliente;
        } catch (\Exception $e) {
            return null;
        }
    }
}
