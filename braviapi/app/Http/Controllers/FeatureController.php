<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\DomainFeature;
use Packk\Core\Models\Domain;
use Packk\Core\Models\Feature;
use Packk\Core\Exceptions\RuleException;

class FeatureController extends Controller
{
    public function __construct()
    {

    }

    /**
     * @OA\Get(
     *   path="/features", operationId="index_features",summary="list Feature",tags={"Feature"},
     *   @OA\Response(response=200,description="A list with Feature",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreFeature")
     *     ),
     *   )
     * )
     */
    public function index()
    {
        return Feature::all();
    }

    /**
     * @OA\Post(
     *   path="/features",operationId="store_features",summary="store Feature",tags={"Feature"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Feature",required=true,@OA\JsonContent(ref="#/components/schemas/StoreFeature")
     *   ),
     *   @OA\Response(response=200,description="A list with Features",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreFeature"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, Feature::storeRules());
        $feature = Feature::create($payload);
        return $feature;
    }

    /**
     * @OA\Get(
     *   path="/features/{FeatureId}",operationId="show_features",summary="list a Feature",tags={"Feature"},
     *   @OA\Parameter(
     *      name="FeatureId",in="path",description="Feature id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with Feature",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowFeature"
     *     ),
     *   )
     * )
     */
    public function show($id)
    {
        $Feature = Feature::findOrFail($id);
        return $Feature;
    }

    /**
     * @OA\Put(
     *   path="/features/{FeatureId}",operationId="update_features",summary="update a Feature",tags={"Feature"},
     *   @OA\Parameter(
     *      name="FeatureId",in="path",description="Feature id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Feature",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateFeature"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, Feature::updateRules());
        $feature  = Feature::where("id", $id)
                            ->update($payload);
        return $feature;
    }

    /**
     * @OA\Delete(
     *   path="/features/{FeatureId}",operationId="destroy_features",summary="destroy Feature",tags={"Feature"},
     *   @OA\Parameter(
     *      name="FeatureId",in="path",description="Feature ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Feature",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $feature = Feature::findOrFail($id);

            // Verifica se está em uso
            $hasFeatureAssociated = DomainFeature::where('feature_id', $feature->id)->first();

            if ($hasFeatureAssociated) {
                throw new RuleException("Funcionalidade associada a domínio", "Não é possível deletar uma funcionalidade já associada.", 1001);
            }else{
                $feature->delete();
                return response(true);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function domainFeature(Request $request, $id){
        if(isset($request->start) and isset($request->length)){
            $total = $request->start/$request->length;
            $page = ($total+1) > 0 ? ceil($total) + 1 : 1;

            $request->merge([
                'page' => $page
            ]);
        }

        return Domain::select('domains.id', 'domains.title as name')
            ->selectRaw('IF(domain_feature.enabled = 1, true, false) as enabled')
            ->leftJoin('domain_feature', function($query) use($id) {
                $query->on('domain_feature.domain_id', '=', 'domains.id')
                    ->where('domain_feature.feature_id', $id);
            })
            ->identic('domain_feature.domain_id', $request->domain_id)
            ->paginate($request->length);
    }

    public function domainFeatureEdit(Request $request, $id, $domain_id){
        try {
            DomainFeature::updateOrCreate(
                ["feature_id" => $id, "domain_id" => $domain_id], 
                ["enabled" => $request->enabled]
            );
        } catch (\Throwable $th) {
            throw $th;
        }

        return ["success" => true];
    }
}