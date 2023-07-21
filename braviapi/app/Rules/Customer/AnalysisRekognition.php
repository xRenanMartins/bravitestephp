<?php

namespace App\Rules\Customer;

use Packk\Core\Exceptions\RuleException;
use Packk\Core\Models\Presenter\ExceptionPresenter;
use Packk\Core\Jobs\Customer\CheckDatavalid;
use Packk\Core\Integration\Freshdesk\Freshdesk;
use Packk\Core\Models\Rekognition;
use Packk\Core\Models\User;
use Illuminate\Support\Str;

class AnalysisRekognition
{

    private $messageTicket;
    private $messageTicketBannedFaces;

    public function execute($payload, $isFile, $user)
    {
        try {

            $data = ["image" => $payload["foto_perfil"], "isFile" => $isFile, "userId" => "none"];
            $rekognition = new Rekognition();
            $faces = $rekognition->analysis($data);

            $checkFace = $this->checkFace($faces, $user);

            if ($checkFace == true) {
                $client = $user->customer();

                // coloca o cliente com status = EM_ANALISE caso tenha alguma suspeita na foto
                if (!empty($this->messageTicket)) {
                    $user = $this->analyseUser($client);
                }

                // procura foto do cliente entre os banidos
                $user = $this->searchBannedFaces($client);

                // envia a foto do cliente ativo para uma collection do rekognition e escaneia a face dele para comparacao futura
                if ($user->status == "ATIVO") {
                    $this->indexActiveFace($client);
                }

                if (!empty($this->messageTicket)) {
                    $this->createTicket($user);
                }
            }

            return $checkFace;
        } catch (\Exception$e) {
            $this->messageTicket .= "- " . $e->getMessage();
            if ($e instanceof RuleException) {
                $this->createTicket($user);
            }
            throw new RuleException("Ops...", $e->getMessage(), 430);
        }
    }

    private function checkFace($faces, $user)
    {
        $this->messageTicket = null;
        if (count($faces) <= 0) {
            $this->messageTicket .= "- Foto sem rosto: Rosto não encontrado. <br>";
            throw new RuleException(ExceptionPresenter::getTitle("FACE_NOTFOUND"), ExceptionPresenter::getMessage("FACE_NOTFOUND"), 430);
        }

        if (isset($faces[0]["Quality"])) {
            if (isset($faces[0]["Quality"]["Brightness"]) && $faces[0]["Quality"]["Brightness"] < $user->domain->getSetting("minimum_rate_brightness_rekognition", 30)) {
                $this->messageTicket .= "- Foto sem qualidade: Brilho da foto está baixo. <br>";
                throw new RuleException(ExceptionPresenter::getTitle("FACE_MINIMUM_RATE_BRIGHTNESS"), ExceptionPresenter::getMessage("FACE_MINIMUM_RATE_BRIGHTNESS"), 430);
            }

            if (isset($faces[0]["Quality"]["Sharpness"]) && $faces[0]["Quality"]["Sharpness"] < $user->domain->getSetting("minimum_rate_sharpness_rekognition", 30)) {
                $this->messageTicket .= "- Foto sem qualidade: A nitidez da foto está baixa. <br>";
                throw new RuleException(ExceptionPresenter::getTitle("FACE_MINIMUM_RATE_SHARPNESS"), ExceptionPresenter::getMessage("FACE_MINIMUM_RATE_SHARPNESS"), 430);
            }
        }

        if (isset($faces[0]["Sunglasses"]) && $faces[0]["Sunglasses"]["Value"] && ($faces[0]["Sunglasses"]["Confidence"] > $user->domain->getSetting("minimun_rate_sunglasses_rekognition", 70))) {
            $this->messageTicket .= "- Foto não aceita: O usuário está com óculos escuros. <br>";
            throw new RuleException(ExceptionPresenter::getTitle("FACE_WITH_SUNGLASSES"), ExceptionPresenter::getMessage("FACE_WITH_SUNGLASSES"), 430);
        }

        if (isset($faces[0]["MouthOpen"]) && $faces[0]["MouthOpen"]["Confidence"] < $user->domain->getSetting("minimun_rate_mouth_rekognition", 90)) {
            $this->messageTicket .= "- Foto não aceita: Não foi possível visualizar a boca. <br>";
            throw new RuleException(ExceptionPresenter::getTitle("FACE_WITHOUT_MOUTH"), ExceptionPresenter::getMessage("FACE_WITHOUT_MOUTH"), 430);
        }

        $confidence = isset($faces[0]['Confidence']) ? $faces[0]['Confidence'] : 0;
        $minimumRateRekognition = $user->domain->getSetting("minimun_rate_mouth_rekognition", 95);
        if ($confidence < $minimumRateRekognition) {
            $this->messageTicket .= "- Rosto não confiável: A foto possui {$confidence}% de confiança, menos que {$minimumRateRekognition}%. <br>";
            throw new RuleException(ExceptionPresenter::getTitle("FACE_NOT_CONFIDENT"), ExceptionPresenter::getMessage("FACE_NOT_CONFIDENT"), 430);
        }

        if (isset($faces[0]["Gender"]) && ($faces[0]["Gender"]["Confidence"] >= $user->domain->getSetting("minimum_rate_rekognition", 95))) {
            $gender = $faces[0]["Gender"]["Value"];
            if (!is_null($user->cpf_gender) && $user->cpf_gender != "U") {
                if (!Str::startsWith(strtolower($gender), strtolower($user->cpf_gender))) {
                    $this->messageTicket .= "- Gênero: {$gender} diferente do cadastro do cpf: {$user->cpf_gender}. <br>";
                }
            }
        }

        return true;
    }

    private function searchBannedFaces($client)
    {
        $user = $client->user;
        $searchFace = (new SearchBannedFaces)->execute($client);
        if (isset($searchFace['FaceMatches']) && count($searchFace['FaceMatches']) > 0) {
            $user->status = "BANIDO";
            $this->messageTicket .= "- Rosto bloqueado <br>";
            $this->prepareTicketBannedFaces($searchFace['FaceMatches']);
        }

        return $user;
    }

    private function prepareTicketBannedFaces($faces)
    {
        $message = "<br><br>Rostos banidos anteriormente:";
        foreach ($faces as $face) {
            $user = User::where("banned_face_id", $face["Face"]["FaceId"])->first();
            if (isset($user)) {
                $message .= "<br><br>";
                $message .= "Nome: {$user->nome} <br>";
                $message .= "Nome Cpf: {$user->cpf_name} <br>";
                $message .= "Telefone: {$user->telefone} <br>";
                $message .= "Email: {$user->email} <br>";
                $message .= "Foto Perfil: {$user->foto_perfil}";
            }
        }

        $this->messageTicketBannedFaces .= $message;
    }

    private function analyseUser($client)
    {
        $user = User::find($client->user->id);
        if ($client->domain->getSetting('has_analyse_costumer', false)) {
            // altera o status do cliente para EM_ANALISE se o cadastro for invalido
            $payloadAnalyseCustomer = collect([]);
            $payloadAnalyseCustomer->cliente = $client;
            $payloadAnalyseCustomer->motivo = explode('<br>', $this->messageTicket);
            (new AnalyseCustomer)->execute($payloadAnalyseCustomer);

            $user->status = "EM_ANALISE";

            if ($client->domain->getSetting('check_datavalid', false) && env("APP_ENV") == 'production') {
                dispatch(new CheckDatavalid($client));
            }
        }
        return $user;
    }

    private function indexActiveFace($client)
    {
        $user = User::find($client->user->id);
        // envia a foto do cliente ativo para uma collection do rekognition e escaneia a face dele para comparacao futura
        $indexFaces = (new Rekognition)->indexFaces('ClientesAtivos', $client);
        if (isset($indexFaces['result']['FaceRecords'][0]['Face']['FaceId'])) {
            $user->active_face_id = $indexFaces['result']['FaceRecords'][0]['Face']['FaceId'];
            $user->save();
        }
    }

    private function createTicket($user)
    {
        $domain = $user->domain;
        $freshdesk = new Freshdesk($domain->id);
        $prepareCreateTicket = $freshdesk->prepareCreateTicket($user);
        if ($domain->hasFeature("freshchat_rekognition")) {
            $freshdesk_credentials = $domain->getSetting('freshdesk_credentials', []);
            $freshdesk->create_ticket(
                $prepareCreateTicket["name"],
                $domain->getSetting('email_sender_freshdesk', 'monitoramento@zaitt.com.br'),
                "Cadastro Cliente inválido - {$domain->title}",
                ($this->messageTicket . "<br>" . $prepareCreateTicket["description"] . $this->messageTicketBannedFaces),
                2, // statusTicket - Aberto
                4, // nivelUrgencia - Urgente
                $freshdesk_credentials->group_id ?? 2043001657449, //groupId
                ['cf_responsvel_pela_resposta' => $domain->getSetting('freshdesk_responsible_to_response', 'Vinicius')]//customFields
            );
        }
    }
}
