<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessZoneStatusUpdate;
use App\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\FirebaseTopic;
use Packk\Core\Models\AreaServed;
use Packk\Core\Models\Mongo\StoreFeed;
use Packk\Core\Models\UserFranchise;
use Packk\Core\Util\Descartes;

class ServiceAreaController extends Controller
{
    /**
     * @OA\Get(
     *   path="/service_areas", operationId="index_service_areas",summary="list ServiceArea",tags={"ServiceArea"},
     *   @OA\Response(response=200,description="A list with ServiceArea",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreServiceArea")
     *     ),
     *   )
     * )
     */
    public function index(Request $request)
    {
        return AreaServed::query()
            ->like('identificador', $request->identificador)
            ->like('cidade', $request->cidade)
            ->identic('tipo', $request->tipo)
            ->simplePaginate(20);
    }

    /**
     * @OA\Post(
     *   path="/service_areas",operationId="store_service_areas",summary="store ServiceArea",tags={"ServiceArea"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the ServiceArea",required=true,@OA\JsonContent(ref="#/components/schemas/StoreServiceArea")
     *   ),
     *   @OA\Response(response=200,description="A list with ServiceAreas",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreServiceArea"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, AreaServed::storeRules());
        if (isset($payload['desc_poligono']) || isset($payload['multi_polygon']) ||
            !array_key_exists('desc_poligono', $payload) || !array_key_exists('multi_polygon', $payload)) {
            $payload['multi_polygon'] = "(-16.315505283627022 -31.801735631223256,-22.284628458340887 -31.977516881223256,-22.040437575930824 -24.858376256223256,-16.315505283627022 -31.801735631223256)";
            $payload['desc_poligono'] = $payload['multi_polygon'];
        }
        $payload = $this->convertArrayLatLngToBinary($payload, true);
        return AreaServed::create($payload);
    }

    /**
     * @OA\Get(
     *   path="/service_areas/{ServiceAreaId}",operationId="show_service_areas",summary="list a ServiceArea",tags={"ServiceArea"},
     *   @OA\Parameter(
     *      name="ServiceAreaId",in="path",description="ServiceArea id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with ServiceArea",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowServiceArea"
     *     ),
     *   )
     * )
     */
    public function show($id)
    {
        return AreaServed::findOrFail($id);
    }

    /**
     * @OA\Put(
     *   path="/service_areas/{ServiceAreaId}",operationId="update_service_areas",summary="update a ServiceArea",tags={"ServiceArea"},
     *   @OA\Parameter(
     *      name="ServiceAreaId",in="path",description="ServiceArea id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A ServiceArea",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateServiceArea"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, AreaServed::updateRules());
        try {
            $serviceArea = AreaServed::findOrFail($id);
            $payload = $this->convertArrayLatLngToBinary($payload, false);

            $serviceArea->update($payload);

            return response()->json([
                "message" => "Zona de atendimento atualizada"
            ], 200);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Delete(
     *   path="/service_areas/{ServiceAreaId}",operationId="destroy_service_areas",summary="destroy ServiceArea",tags={"ServiceArea"},
     *   @OA\Parameter(
     *      name="ServiceAreaId",in="path",description="ServiceArea ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted ServiceArea",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $serviceArea = AreaServed::findOrFail($id);
            $serviceArea->delete();
            dispatch(function () use($id) {
                Artisan::call("zone:updated {$id}");
            });

            return response()->json([
                "message" => "Zona de atendimento removida"
            ], 200);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Essa função transforma o string de lat e lng no formato binário
     *
     * @param [type] $payload
     * @return array
     */
    private function convertArrayLatLngToBinary($payload, $create = true)
    {
        if (isset($payload['desc_poligono'])) {
            $payload['multi_polygon'] = DB::raw("ST_GeomFromText('MULTIPOLYGON(({$payload['desc_poligono']}))')");
        } else {
            if ($create == true) {
                $payload['multi_polygon'] = null;
            } else {
                if (isset($payload['multi_polygon'])) {
                    unset($payload['multi_polygon']);
                }
            }
        }

        if (isset($payload['desc_polygon_suspicious_area'])) {
            $payload['multi_polygon_suspicious_area'] = DB::raw("ST_GeomFromText('MULTIPOLYGON(({$payload['desc_polygon_suspicious_area']}))')");
        } else {
            if ($create == true) {
                $payload['multi_polygon_suspicious_area'] = null;
            } else {
                if (isset($payload['multi_polygon_suspicious_area'])) {
                    unset($payload['multi_polygon_suspicious_area']);
                }
            }
        }

        if ((!isset($payload['desc_poligono']) &&
                !isset($payload['desc_polygon_suspicious_area'])) || !isset($payload['content_zone_rule'])) {
            if ($create == true) {
                $payload['content_zone_rule'] = null;
            } else {
                if (isset($payload['content_zone_rule'])) {
                    unset($payload['content_zone_rule']);
                }
            }
        }

        return $payload;
    }

    public function firebaseTopic(Request $request)
    {
        return FirebaseTopic::query()
            ->when(isset($request->type), function ($query) use ($request) {
                $query->where('type', Str::upper($request->type));
            })->select('id', 'name as description')->get();
    }

    public function getFranchises()
    {
        return UserFranchise::query()
            ->join('franchises', 'franchises.id', '=', 'user_franchises.franchise_id')
            ->whereHas('user', function ($q) {
                $q->whereHas('dbRoles', function ($q) {
                    $q->where('name', 'admin-franchise');
                });
            })->select('franchises.id', 'franchises.name', 'franchises.firebase_topic_id')->get();
    }

    public function verifyUse(Request $request, $zoneId)
    {
        $currentZone = AreaServed::query()
            ->selectRaw("id,tipo,ST_X(ST_Centroid(multi_polygon)) as latitude,ST_Y(ST_Centroid(multi_polygon)) as longitude")
            ->find($zoneId);

        $quantityZones = StoreFeed::where('zone_id', intval($zoneId))->count();
        $zones = AreaServed::where('tipo', $currentZone->tipo)->where('id', '<>', $currentZone->id)
            ->selectRaw("id,identificador,ST_X(ST_Centroid(multi_polygon)) as latitude,ST_Y(ST_Centroid(multi_polygon)) as longitude")
            ->get();

        $resultZones = [];
        foreach ($zones as $topic) {
            $distance = round(Descartes::distance($currentZone->latitude, $currentZone->longitude, $topic->latitude, $topic->longitude), 8);
            if ($distance < 20) {
                $resultZones[] = $topic;
            }
        }

        return ApiResponse::sendResponse([
            'quantity' => $quantityZones,
            'zones' => $resultZones,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $zone = AreaServed::find($id);
            $newStatus = (bool)$request->get('active', 1);
            $zone->update(['atendido' => $newStatus]);

            if ($newStatus || !empty($request->new_zone_id)) {
                dispatch(new ProcessZoneStatusUpdate([
                    'zone_id' => $id,
                    'new_zone_id' => $request->new_zone_id,
                    'new_status' => $newStatus
                ]));
            }

            return ApiResponse::sendResponse();
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }
}
