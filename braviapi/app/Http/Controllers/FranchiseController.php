<?php

namespace App\Http\Controllers;

use App\Rules\Franchise\StoreFranchise;
use App\Rules\Franchise\TransfersResume;
use App\Rules\Franchise\UpdateFranchise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\Store;
use Packk\Core\Models\UserFranchise;

class FranchiseController extends Controller
{
    /**
     * @OA\Get(
     *   path="/usersFranchise", operationId="index_franchise",summary="list Franchisee User",tags={"User"},
     *   @OA\Response(response=200,description="A list with User",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreUser")
     *     ),
     *   )
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $user_query = UserFranchise::query()
            ->join('franchises', 'franchises.id', '=', 'user_franchises.franchise_id')
            ->whereHas('user', function ($q) {
                $q->whereHas('dbRoles', function ($q) {
                    $q->where('name',  'admin-franchise');
                });
            })->with('user')
            ->with('franchise.address')
            ->with('franchise.firebase_topic');

        $franchise = $user->getFranchise();
        if (!empty($franchise)) {
            $user_query->where('franchises.id', $franchise->id);
        }

        if (isset($request['nameFranchise']) && !empty($request['nameFranchise'])) {
            $user_query->where(function ($q) use ($request) {
                $q->where('franchises.name', 'like', "%{$request['nameFranchise']}%");
                $q->orWhere('franchises.fantasy_name', 'like', "%{$request['nameFranchise']}%");
            });
        }
        if (isset($request['nameFranchisee']) && !empty($request['nameFranchisee'])) {
            $user_query->whereHas('user', function ($q) use ($request) {
                $q->where('nome', 'like', "%{$request['nameFranchise']}%");
                $q->orWhere('sobrenome', 'like', "%{$request['nameFranchise']}%");
            });
        }
        if (isset($request['id']) && !empty($request['id'])) {
            $user_query->where('id', $request['id']);
        }
        if (isset($request['cnpj']) && $request['cnpj'] != '') {
            $user_query->where('franchises.cnpj', $request['cnpj']);
        }
        if (isset($request['status']) && $request['status'] != '') {
            $user_query->where('franchises.active', $request['status']);
        }
        if (isset($request['states']) && !empty($request['states'])) {
            $user_query->whereHas('franchise', function ($q) use ($request) {
                $q->whereHas('address', function ($q) use ($request) {
                    $q->whereIn('state', explode(',', $request['states']));
                });
            });
        }
        if (isset($request['cities']) && !empty($request['cities'])) {
            $user_query->whereHas('franchise', function ($q) use ($request) {
                $q->whereHas('address', function ($q) use ($request) {
                    $q->whereIn('cidade', explode(',', $request['cities']));
                });
            });
        }

        return $user_query->simplePaginate($request->length);
    }

    /**
     * @OA\Post(
     *   path="/usersFranchise",operationId="store_franchise",summary="store Franchisee User",tags={"User"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the User",required=true,@OA\JsonContent(ref="#/components/schemas/StoreUser")
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
        $payload = $this->validate($request, Franchise::storeRules());
        return (new StoreFranchise())->execute($payload);
    }

    /**
     * @OA\Post(
     *   path="/usersFranchise",operationId="update_franchise",summary="Update Franchisee User",tags={"User"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the User",required=true,@OA\JsonContent(ref="#/components/schemas/StoreUser")
     *   ),
     *   @OA\Response(response=200,description="A list with Users",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreUser"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, Franchise::updateRules());
        $payload['password'] = $payload['confirm_password'];

        return (new UpdateFranchise())->execute($payload);
    }

    public function list()
    {
        $user = Auth::user();
        $franchise = $user->getFranchise();

        return Franchise::query()
            ->when(!empty($franchise), function ($query) use ($franchise) {
                $query->where('id', $franchise->id);
            })->select('id', 'name', 'firebase_topic_id')->get()->toArray();
    }

    public function cities()
    {
        return DB::table('enderecos')
            ->join('franchises', 'franchises.address_id', '=', 'enderecos.id')
            ->selectRaw('cidade')->groupBy('cidade')->get()->toArray();
    }

    public function reportTransfers(Request $request, TransfersResume $transfersResume)
    {
        if (isset($request->start) and isset($request->length)) {
            $total = $request->start / $request->length;
            $page = ($total + 1) > 0 ? ceil($total) + 1 : 1;
            $request->merge([
                'page' => $page
            ]);
        }
        $payload = $this->payload($request);
        return $transfersResume->execute($payload);
    }

    public function stores(Request $request)
    {
        $user = Auth::user();
        $franchise = $user->getFranchise();
        if (!empty($franchise)) {
            $request->merge(['franchise_id' => $franchise->id]);
        }
        return Store::select(['id', 'nome', 'franchise_id'])->like('nome', $request->name)
            ->when(!empty($request->franchise_id), function ($query) use ($request) {
                $query->where('franchise_id', $request->franchise_id);
            })->whereNotNull('franchise_id')->orderBy('nome')->get()->toArray();
    }
}
