<?php

namespace App\Rules\Customer;

use Packk\Core\Models\Reason;
use Packk\Core\Models\Rekognition;
use App\Models\User;

class SearchActiveFaces
{
    public function execute($cliente)
    {
        try {
            $domain = currentDomain(true);
            $searchFace = (new Rekognition($domain->id, 99))->searchFaceInCollection($cliente, 'ClientesAtivos');
            $Similarity = false;
            $FaceId = null;

            if (isset($searchFace['FaceMatches']) && count($searchFace['FaceMatches']) > 0) {
                foreach ($searchFace['FaceMatches'] as $face) {
                    if ($face['Similarity'] >= 99) {
                        $Similarity = true;
                        $FaceId = isset($face['Face']['FaceId']) ? $face['Face']['FaceId'] : null;
                    }
                }
            }

            if ($Similarity && !empty($FaceId)) {
                $user = User::where('active_face_id', $FaceId)->first();
                if ($user) {
                    $client = $user->cliente();
                    return $client;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
