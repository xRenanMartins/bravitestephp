<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\ServiceArea;
use Packk\Core\Models\ZoneDistance;

class ServiceAreaDistanceController extends Controller
{
    /**
     * @OA\Get(
     *   path="/service_area_distances", operationId="index_service_area_distances",summary="list ServiceAreaDistance",tags={"ServiceAreaDistance"},
     *   @OA\Response(response=200,description="A list with ServiceAreaDistance",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreServiceAreaDistance")
     *     ),
     *   )
     * )
     */
    public function index()
    {
        return ZoneDistance::get();
    }

    /**
     * @OA\Post(
     *   path="/service_area_distances",operationId="store_service_area_distances",summary="store ServiceAreaDistance",tags={"ServiceAreaDistance"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the ServiceAreaDistance",required=true,@OA\JsonContent(ref="#/components/schemas/StoreServiceAreaDistance")
     *   ),
     *   @OA\Response(response=200,description="A list with ServiceAreaDistances",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreServiceAreaDistance"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, ZoneDistance::storeRules());
        $serviceAreaDistance = ZoneDistance::create($payload);
        return $serviceAreaDistance;
    }

    /**
     * @OA\Get(
     *   path="/service_area_distances/{ServiceAreaDistanceId}",operationId="show_service_area_distances",summary="list a ServiceAreaDistance",tags={"ServiceAreaDistance"},
     *   @OA\Parameter(
     *      name="ServiceAreaDistanceId",in="path",description="ServiceAreaDistance id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with ServiceAreaDistance",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowServiceAreaDistance"
     *     ),
     *   )
     * )
     */
    public function show($id)
    {
        $serviceAreaDistance = ZoneDistance::findOrFail($id);
        return $serviceAreaDistance;
    }

    /**
     * @OA\Get(
     *   path="/service_area_distances/{ServiceAreaId}",operationId="list_service_area_distances",summary="list a ServiceAreaDistance linked with a service area",tags={"ServiceAreaDistance"},
     *   @OA\Parameter(
     *      name="ServiceAreaId",in="path",description="ServiceArea id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with ServiceAreaDistance",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowServiceAreaDistance"
     *     ),
     *   )
     * )
     */
    public function list($serviceAreaId)
    {
        $rows = ZoneDistance::where('zona_atendida_id', $serviceAreaId)->get();
        return $rows;
    }

    /**
     * @OA\Put(
     *   path="/service_area_distances/{ServiceAreaDistanceId}",operationId="update_service_area_distances",summary="update a ServiceAreaDistance",tags={"ServiceAreaDistance"},
     *   @OA\Parameter(
     *      name="ServiceAreaDistanceId",in="path",description="ServiceAreaDistance id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A ServiceAreaDistance",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateServiceAreaDistance"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, ZoneDistance::updateRules());
        $serviceAreaDistance = ZoneDistance::where("id", $id)->update($payload);
        return $serviceAreaDistance;
    }

    /**
     * @OA\Delete(
     *   path="/service_area_distances/{ServiceAreaDistanceId}",operationId="destroy_service_area_distances",summary="destroy ServiceAreaDistance",tags={"ServiceAreaDistance"},
     *   @OA\Parameter(
     *      name="ServiceAreaDistanceId",in="path",description="ServiceAreaDistance ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted ServiceAreaDistance",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $serviceAreaDistance = ZoneDistance::findOrFail($id);
            $serviceAreaDistance->delete();
            return response(true);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
