<?php

namespace App\Rules\Customer;

use Packk\Core\Models\Reason;
use Packk\Core\Models\Rekognition;

class SearchBannedFaces
{
    public function execute($cliente)
    {
        try {
            $searchFace = (new Rekognition($cliente->domain_id, $cliente->domain->getSetting("face_match_similarity", 85)))->searchFaceInCollection($cliente, 'ClientesBanidos');
            // se encontrar a foto do cliente entre os clientes banidos ele tbm eh banido
            if (isset($searchFace['FaceMatches']) && count($searchFace['FaceMatches']) > 0) {
                $ban_reason = Reason::where('domain_id', $cliente->domain_id)->where([['service_provider', 'ADMIN'], ['tipo', 'ADMIN_CLIENTE']])->first();

                $payload = collect([]);
                $payload->reason = $ban_reason->id;
                $payload->cliente_id = $cliente->id;
                $payload->descricao = 'Rosto bloqueado';

                (new BanCustomer)->execute($payload);
            }
            return $searchFace;
        } catch (\Exception $e) {
            return null;
        }
    }
}
