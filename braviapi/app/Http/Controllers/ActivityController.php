<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Activity;
use Packk\Core\Scopes\DomainScope;

class ActivityController extends Controller
{
    /**
     * @OA\Get(
     *   path="/atividades", operationId="index_atividades",summary="list Atividade",tags={"Atividade"},
     *   @OA\Response(response=200,description="A list with Atividade",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreAtividade")
     *     ),
     *   )
     * )
     */
    public function index(Request $request)
    {
        return Activity::withoutGlobalScope(DomainScope::class)
            ->join('domains', 'domains.id', '=', 'atividades.domain_id')
            ->identic('atividades.id', $request->id)
            ->identic('domain_id', $request->domain_id)
            ->identic('scope', $request->scope)
            ->identic('reason_id', $request->reason_id)
            ->like('nome', $request->nome)
            ->like('flag', $request->flag)
            ->orderByDesc('id')
            ->selectRaw('atividades.*, domains.title as domain_desc')
            ->simplePaginate($request->length);
    }

    /**
     * @OA\Post(
     *   path="/atividades",operationId="store_atividades",summary="store Atividade",tags={"Atividade"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Atividade",required=true,@OA\JsonContent(ref="#/components/schemas/StoreAtividade")
     *   ),
     *   @OA\Response(response=200,description="A list with Atividades",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreAtividade"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, Activity::storeRules());
        $payload['flag'] = mb_strtoupper($payload['flag']);
        $payload['type'] = mb_strtoupper($payload['type']);
        $activity = Activity::withoutGlobalScope(DomainScope::class)->create($payload);
        return response([
            'success' => true,
            'activity' => $activity
        ]);
    }

    public function storeOtherDomain(Request $request)
    {
        $payload = Activity::withoutGlobalScope(DomainScope::class)
            ->where('domain_id', $request->otherDomainId)
            ->whereIn('id', explode(',', $request->activities))
            ->get()->toArray();

        foreach ($payload as $value) {
            $value['domain_id'] = $request->domain_id;
            Activity::create($value);
        }
        return response([
            'success' => true,
        ]);
    }

    /**
     * @OA\Put(
     *   path="/atividades/{AtividadeId}",operationId="update_atividades",summary="update a Atividade",tags={"Atividade"},
     *   @OA\Parameter(
     *      name="AtividadeId",in="path",description="Atividade id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Atividade",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateAtividade"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, Activity::updateRules());
        $payload['flag'] = mb_strtoupper($payload['flag']);
        $payload['type'] = mb_strtoupper($payload['type']);
        $activity = Activity::withoutGlobalScope(DomainScope::class)->where("id", $id)->update($payload);
        return response([
            'success' => true,
            'activity' => $activity
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/atividades/{AtividadeId}",operationId="destroy_atividades",summary="destroy Atividade",tags={"Atividade"},
     *   @OA\Parameter(
     *      name="AtividadeId",in="path",description="Atividade ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Atividade",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $activity = Activity::withoutGlobalScope(DomainScope::class)->findOrFail($id);
            $activity->delete();
            return response([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
