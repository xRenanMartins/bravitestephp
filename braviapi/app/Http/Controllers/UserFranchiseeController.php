<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Role;
use Packk\Core\Models\User;
use Packk\Core\Models\RoleUser;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\UserFranchise;

class UserFranchiseeController extends Controller
{
    /**
     * @OA\Get(
     *   path="/users_franchise", operationId="index_frachisee_users",summary="list Franchisee User",tags={"User"},
     *   @OA\Response(response=200,description="A list with User",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreUser")
     *     ),
     *   )
     * )
     */
    public function index(Request $request)
    {

    }

    /**
     * @OA\Post(
     *   path="/users_franchise",operationId="store_frachisee_users",summary="store Franchisee User",tags={"User"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Franchise User",required=true,@OA\JsonContent(ref="#/components/schemas/StoreUser")
     *   ),
     *   @OA\Response(response=200,description="A list with Users",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreUser"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {

    }

    /**
     * @OA\Post(
     *   path="/users_franchise",operationId="delete_frachisee_users",summary="delte Franchisee User",tags={"User"},
     *   @OA\RequestBody(
     *         description="Exemple to delete franchise user",required=true,@OA\JsonContent(ref="#/components/schemas/StoreUser")
     *   ),
     *   @OA\Response(response=200,description="A list with Users",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreUser"
     *     ),
     *   )
     * )
     */
    public function destroy($id)
    {
        $user = User::query()->findOrFail($id);
        $user->delete();
        return response(true);
    }
}
