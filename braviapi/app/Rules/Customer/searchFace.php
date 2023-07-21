<?php

namespace App\Rules\Customer;

use Illuminate\Http\Request;
use Packk\Core\Models\Rekognition;
use Packk\Core\Models\User;

class searchFace
{
    public function execute(Request $request)
    {
        $customer = collect();
        $customer->user = collect();
        $customer->user->foto_perfil = $request->type == "file" ? $request->photo->getRealPath() : $request->photo_url;

        $domain = currentDomain(true);

        $FacesId = [];
        foreach (['ClientesAtivos', 'ClientesBanidos'] as $CollectionId) {
            $searchFace = (new Rekognition($domain->id, 99))->searchFaceInCollection($customer, $CollectionId, 0);
            if (isset($searchFace['FaceMatches']) && count($searchFace['FaceMatches']) > 0) {
                foreach ($searchFace['FaceMatches'] as $face) {
                    if ($face['Similarity'] >= 99) {
                        if (isset($face['Face']['FaceId'])) {
                            array_push($FacesId, $face['Face']['FaceId']);
                        }
                    }
                }
            }
        }

        $users = User::select('id')
            ->whereIn("active_face_id", $FacesId)
            ->orWhereIn("banned_face_id", $FacesId)
            ->get()->pluck('id');

        return response()->json(["users" => $users]);
    }
}