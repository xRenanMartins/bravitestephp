<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Reason;
use Packk\Core\Scopes\DomainScope;
use function Clue\StreamFilter\fun;

class ReasonController extends Controller
{
    /**
     * @OA\Get(
     *   path="/reasons", operationId="index_reasons",summary="list Reason",tags={"Reason"},
     *   @OA\Response(response=200,description="A list with Reason",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreReason")
     *     ),
     *   )
     * )
     */
    public function index(Request $request)
    {
        $query = Reason::withoutGlobalScope(DomainScope::class)
            ->join('domains', 'domains.id', '=', 'reasons.domain_id')
            ->when(!empty($request->ban), function ($q) {
                $q->where('ban_time', '>', 0);
            })->identic('tipo', $request->tipo)
            ->identic('service_provider', $request->service_provider)
            ->like('descricao', $request->descricao)
            ->identic('domain_id', $request->domain_id)
            ->orderBy('descricao', 'asc')
            ->selectRaw('reasons.*, domains.title as domain_desc');
        return $query->simplePaginate($request->length);
    }

    /**
     * @OA\Post(
     *   path="/reasons",operationId="store_reasons",summary="store Reason",tags={"Reason"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Reason",required=true,@OA\JsonContent(ref="#/components/schemas/StoreReason")
     *   ),
     *   @OA\Response(response=200,description="A list with Reasons",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreReason"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $this->validate($request, Reason::storeRules());
        Reason::withoutGlobalScope(DomainScope::class)->create($request->all());
        return response([
            'success' => true,
        ]);
    }

    /**
     * @OA\Put(
     *   path="/reasons/{ReasonId}",operationId="update_reasons",summary="update a Reason",tags={"Reason"},
     *   @OA\Parameter(
     *      name="ReasonId",in="path",description="Reason id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Reason",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateReason"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, Reason::updateRules());
        Reason::withoutGlobalScope(DomainScope::class)->where("id", $id)->update($request->all());
        return response([
            'success' => true,
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/reasons/{ReasonId}",operationId="destroy_reasons",summary="destroy Reason",tags={"Reason"},
     *   @OA\Parameter(
     *      name="ReasonId",in="path",description="Reason ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Reason",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $atividade = Reason::withoutGlobalScope(DomainScope::class)->findOrFail($id);
            $atividade->delete();
            return response([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}