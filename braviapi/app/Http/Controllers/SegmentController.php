<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\CompanySegment;

class SegmentController extends Controller
{
    /**
     * @OA\Get(
     *   path="/segments", operationId="index_segments",summary="list Segment",tags={"Segment"},
     *   @OA\Response(response=200,description="A list with Segment",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreSegment")
     *     ),
     *   )
     * )
     */
    public function index()
    {
        return CompanySegment::all();
    }

    /**
     * @OA\Post(
     *   path="/segments",operationId="store_segments",summary="store Segment",tags={"Segment"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Segment",required=true,@OA\JsonContent(ref="#/components/schemas/StoreSegment")
     *   ),
     *   @OA\Response(response=200,description="A list with Segments",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreSegment"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        // $payload = $this->validate($request, Segment::storeRules());
    }

    /**
     * @OA\Get(
     *   path="/segments/{SegmentId}",operationId="show_segments",summary="list a Segment",tags={"Segment"},
     *   @OA\Parameter(
     *      name="SegmentId",in="path",description="Segment id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with Segment",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowSegment"
     *     ),
     *   )
     * )
     */
    public function show($id)
    {
        // $Segment = Segment::findOrFail($id);
        // return $Segment;
    }

    /**
     * @OA\Put(
     *   path="/segments/{SegmentId}",operationId="update_segments",summary="update a Segment",tags={"Segment"},
     *   @OA\Parameter(
     *      name="SegmentId",in="path",description="Segment id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Segment",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateSegment"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        // $payload = $this->validate($request, Segment::updateRules());
    }

    /**
     * @OA\Delete(
     *   path="/segments/{SegmentId}",operationId="destroy_segments",summary="destroy Segment",tags={"Segment"},
     *   @OA\Parameter(
     *      name="SegmentId",in="path",description="Segment ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Segment",
     *   )
     * )
     */
    public function destroy($id)
    {
        // $Segment = Segment::findOrFail($id);
    }
}
