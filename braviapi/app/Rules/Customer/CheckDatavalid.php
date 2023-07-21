<?php

namespace App\Rules\Customer;

use Packk\Core\Models\Reason;
use Packk\Core\Actions\Admin\Customer\BanCustomer;
use Packk\Core\Integration\Datavalid\Datavalid;
use Packk\Core\Traits\Loggable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDatavalid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Loggable;

    private $client;

    public function __construct($client)
    {
        $this->client = $client;
        $this->setType("log_datavalid");
    }

    public function handle()
    {
        $payload = [
            "key" => [
                "cpf" => $this->client->user->cpf,
            ],
            "answer" => [
                "biometria_face" => base64_encode(file_get_contents($this->client->user->foto_perfil)),
            ],
        ];
        $result = (new Datavalid)->do_request("POST", "v2/validate/pf-face", $payload);

        if ($result->status != 200) {
            $this->client->user->addAtividade('USER_NOT_FOUND_DATAVALID', ["[::text]" => "A consulta no Datavalid não encontrou resultado para o usuário"]);
        } else {

            if ($result->data->cpf_disponivel == true) {
                $probability = isset($result->data->biometria_face->probabilidade) ? $result->data->biometria_face->probabilidade : "Muito baixa";
                $similarity = isset($result->data->biometria_face->similaridade) ? intval($result->data->biometria_face->similaridade * 100) : 0;

                if (in_array($probability, ["Baixa probabilidade", "Baixíssima probabilidade"])) {
                    $ban_reason = Reason::where('domain_id', $this->client->domain_id)->where([['service_provider', 'ADMIN'], ['tipo', 'ADMIN_CLIENTE']])->first();
                    $payload = collect([]);
                    $payload->reason = $ban_reason->id;
                    $payload->cliente_id = $this->client->id;
                    $payload->descricao = "Checagem no Datavalid: {$probability}";
                    (new BanCustomer)->execute($payload);
                } elseif (in_array($probability, ["Altíssima probabilidade", "Alta probabilidade"]) && $this->client->user->status == "EM_ANALISE") {
                    (new ActivateCustomer)->execute($this->client->id);
                }

                $this->client->user->addAtividade('USER_DATAVALID', ["[::similarity]" => $similarity]);
            } else {
                $this->client->user->addAtividade('USER_NOT_FOUND_DATAVALID', ["[::text]" => "CPF não encontrado na base de dados do Datavalid"]);
            }
        }
    }
}
