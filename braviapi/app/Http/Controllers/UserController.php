<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use App\Rules\Permission\CheckPermissionRole;
use App\Rules\User\MeResource;
use App\Traits\PermissionObjectTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Packk\Core\Actions\Admin\User\DeleteUser;
use Packk\Core\Events\UserLoggedOut;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\Franchise;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\UserFranchise;
use Packk\Core\Models\User;
use Packk\Core\Models\RoleUser;
use Packk\Core\Models\Role;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Util\Phones;

class UserController extends Controller
{
    use PermissionObjectTrait;

    public function login(Request $request)
    {
        $user = User::withoutGlobalScope(DomainScope::class)->where("email", $request->username)
            ->whereNotIn("tipo", ['C', 'E'])->first();
        if (empty($user)) {
            return ApiResponse::sendError('Email ou senha inválidos');
        }

        if (password_verify($request->password, $user->password) && $user->tipo !== 'L') {
            if ($user->tipo == 'O') {
                $this->logOperador($user);
            }

            // Salva as roles em cache
            $permissionRoles = new CheckPermissionRole($user);
            if (!$permissionRoles->checkPrivilege()) {
                Cache::forget("user.{$user->id}.roles");
                $roles = $user->rolesUser->pluck('role_id')->all();
                $permissionRoles->getRolesChilds($roles);
            }
            Cache::forget("user.{$user->id}.menu");

            $token = $user->createToken('Token Name')->accessToken;
            $isFranchiseOperator = $user->isFranchiseOperator();
            $userSend = [
                'nome' => $user->nome,
                'sobrenome' => $user->sobrenome,
                'email' => $user->email,
                'show_domains' => !$isFranchiseOperator && $user->hasAdminPrivileges(),
                'franchise_role' => $isFranchiseOperator,
                'franchise' => $isFranchiseOperator ? $user->getFranchise() : null,
                'permissions_auth' => self::getPermissionCache($user),
            ];
            if ($user->password_temporario == true) {
                return [
                    "success" => true,
                    "user" => $userSend,
                    "type" => "password",
                    "token" => $token
                ];
            } else {
                return [
                    "success" => true,
                    "user" => $userSend,
                    "type" => "token",
                    "token" => $token
                ];
            }
        } else {
            return [
                "success" => false,
                "type" => "error"
            ];
        }
    }

    /**
     * Undocumented function
     *
     * @param User $user
     * @return void
     */
    private function logOperador(User $user)
    {
        $context = [
            '[::operador]' => $user->nome_completo,
        ];

        $user->addAtividade('OPERATOR_ONLINE', $context, $user->id, 'USER');
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();
        $user->password = bcrypt($request->password);
        $user->password_temporario = false;
        $user->password_updated_at = Carbon::now()->addDays(90);
        $user->save();

        $userExistRoles = $user->dbRoles()
            ->whereIn('name', ['db-access-domain', 'db-access-all', 'db-master'])
            ->get()->pluck('name')->all();
        $email = explode("@", $user->email)[0];
        if (in_array('db-access-domain', $userExistRoles)) {

            DB::select('call grant_access_select(?,?,?)', array(
                $email,
                $request->password,
                'D'
            ));
        }
        if (in_array('db-master', $userExistRoles)) {

            DB::select('call grant_access_select(?,?,?)', array(
                $email,
                $request->password,
                'M'
            ));
        }
        if (in_array('db-access-all', $userExistRoles)) {

            DB::select('call grant_access_select(?,?,?)', array(
                $email,
                $request->password,
                'A'
            ));
        }
        $token = $user->createToken('Token Name')->accessToken;

        return [
            "success" => true,
            "type" => "token",
            "token" => $token
        ];
    }

    /**
     * @OA\Get(
     *   path="/users", operationId="index_users",summary="list User",tags={"User"},
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

        $query = User::query()
            ->identic('users.id', $request->id)
            ->identic('domain_id', $request->domain_id)
            ->like(DB::raw('CONCAT(nome, " ",sobrenome)'), $request->nome)
            ->like('email', $request->email)
            ->like('telefone', $request->telefone)
            ->orderBy('users.nome');
        $query_franchises = [];

        if ($user->isFranchiseOperator()) {
            $query->with('franchisees');

            if ($user->hasRole('admin-franchise-all|admin-franchise-growth')) {
                $query->whereHas('franchisees', function ($q) use ($user, $request) {
                    $q->where('franchise_id', "!=", null);
                    $q->where('user_id', '!=', $user['id']);
                    if (isset($request['franchise_id']) && is_numeric($request['franchise_id'])) {
                        $q->where('franchise_id', "=", $request['franchise_id']);
                    }
                });

                $query_franchises = Franchise::query()->get();
            } else {
                $franchises_id = UserFranchise::query()->where('user_id', '=', $user->id)->pluck('franchise_id');
                $query->whereHas('franchisees', function ($q) use ($user, $franchises_id) {
                    $q->where('user_id', '!=', $user['id']);
                    $q->whereIn('franchise_id', $franchises_id);
                });
                $query_franchises = Franchise::query()->whereIn('id', $franchises_id)->get();
            }
        } else {
            $query->whereIn('tipo', ['A', 'O', 'F', 'M', 'FRANCHISEE']);
        }

        $data = $query->simplePaginate($request->length);
        $response = $data->toArray();
        foreach ($data->items() as $key => $item) {
            $response['data'][$key]['telefone'] = empty($item->telefone) ? null : Phones::formatExibe($item->telefone);
        }

        return [
            'franchises' => $query_franchises,
            'data' => $response
        ];
    }

    /**
     * @OA\Post(
     *   path="/users",operationId="store_users",summary="store User",tags={"User"},
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
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $payload = $this->validate($request, User::storeRules());

            $payload['password'] = bcrypt($request->password);
            $payload['telefone'] = Phones::format($request->telefone);
            $payload['password_temporario'] = 1;
            $payload['tipo'] = $user->hasRole('admin-franchise|operator-franchise') ? 'FRANCHISEE' : 'A';

            $userExists = User::query()
                ->where('email', $payload['email'])
                ->where('tipo', 'A')
                ->withTrashed()->first();
            if (!empty($userExists)) {
                if (!empty($userExists->deleted_at)) {
                    $userExists->update(['email' => $userExists->email . '-' . rand(1, 9)]);
                } else {
                    throw new CustomException('Já existe um usuário cadastrado com esse e-mail');
                }
            }

            $insertUser = User::create($payload);

            if ($user->hasRole('admin-franchise|operator-franchise')) {
                $role = Role::query()->where('name', 'operator-franchise')->first();
                $createRole = new RoleUser();
                $createRole->user_id = $insertUser->id;
                $createRole->role_id = $role->id;
                $createRole->save();

                $franchise = UserFranchise::query()->where('user_id', $user->id)->first();
                $createUserFranchise = new UserFranchise();
                $createUserFranchise->user_id = $insertUser->id;
                $createUserFranchise->franchise_id = $franchise->franchise_id;
                $createUserFranchise->save();
            }

            DB::commit();
            return $insertUser;
        } catch (CustomException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function roles($id)
    {
        $user = Auth::user();
        $inRoles = [];
        $notInRoles = ['owner', 'admin', 'operator'];

        if ($user->hasRole('admin-onboarding')) {
            $inRoles = ["operator-crawler", "operator-onboarding"];
        } else if ($user->hasRole('admin-financial')) {
            $inRoles = ["operator-financial"];
        } else if ($user->hasRole('admin-account')) {
            $inRoles = ["operator-account"];
        } else if ($user->hasRole('admin-logistics')) {
            $inRoles = ["operator-logistics"];
        } else if ($user->hasRole('admin-growth')) {
            $inRoles = ["operator-growth"];
        } else if ($user->hasRole('admin-franchise-all')) {
            $inRoles = ["admin-franchise", "operator-franchise"];
        } else if ($user->hasRole('admin-franchise')) {
            $inRoles = ["operator-franchise"];
        } else {
            if (!$user->hasRole('owner')) {
                $notInRoles[] = 'master';
            }
            if (!$user->hasRole('owner|master')) {
                $notInRoles[] = 'admin-all';
            }
        }

        if (empty($inRoles)) {
            $roles1 = Role::whereNotIn('name', $notInRoles)->orderBy('label', 'asc')->get();
        } else {
            $roles1 = Role::whereIn('name', $inRoles)->orderBy('label', 'asc')->get();
        }
        $roles2 = User::withoutGlobalScope(DomainScope::class)->find($id)->dbRoles()->orderBy('label', 'asc')->get();

        $idsRoles2 = $roles2->pluck('id')->toArray();
        $data = [
            'roles1' => [],
            'roles2' => [],
        ];
        foreach ($roles1 as $role) {
            if (!in_array($role->id, $idsRoles2)) {
                $data['roles1'][] = $role;
            } else {
                $data['roles2'][] = $role;
            }
        }
        Cache::forget("user.{$user->id}.menu");

        return $data;
    }

    public function updateRoles(Request $request, $id)
    {
        $payload = $this->validate($request, [
            'roles' => 'sometimes'
        ]);
        $roles = !empty($payload['roles']) ? explode(',', $payload['roles']) : [];
        $user = User::find($id);
        $userAuth = Auth::user();

        $notInRoles = ['owner', 'admin', 'operator'];
        if (!$userAuth->hasRole('owner')) {
            $notInRoles[] = 'master';
        }
        if (!$userAuth->hasRole('owner|master')) {
            $notInRoles[] = 'admin-all';
        }

        if (count($roles) > 0) {
            $removeDbRole = $user->dbRoles()->whereNotIn("roles.id", $roles)->whereIn("roles.label", ['db-access-domain', 'db-access-all', 'db-master'])->count();

            $rolesIds = $user->dbRoles()->whereIn('roles.label', $notInRoles)->select('roles.id')->get()->pluck('id')->toArray();
            $user->dbRoles()->sync(array_merge($roles, $rolesIds));

            $email = explode("@", $user->email)[0];
            if ($removeDbRole > 0) {
                DB::select('call grant_access_select(?,?,?)', array(
                    $email,
                    $request->password,
                    'E'
                ));
            }
        } else {
            $rolesIds = Role::query()->whereIn('label', $notInRoles)->select('id')->get()->pluck('id');
            $user->rolesUser()->whereNotIn('role_id', $rolesIds)->delete();
        }

        if ($user->dbRoles()->whereIn('name', ['db-access-domain', 'db-access-all'])->count() > 0) {
            $user->password_temporario = true;
            $user->save();
        }

        Cache::forget("user.{$user->id}.menu");
        Cache::forget("user.{$user->id}.roles");
        Cache::forget("user.{$user->id}.permission.key");
        Cache::forget("user.{$user->id}.permission.items");

        $audits = new LogTable;
        $audits->action = "UPDATE";
        $audits->table = "users";
        $audits->column = "role_user";
        $audits->register_id = $id;
        $audits->previus_value = $request->previus_value;
        $audits->after_value = $request->roles;
        $audits->domain_id = currentDomain(true)->id;
        $audits->save();

        return response(true);
    }

    /**
     * @OA\Put(
     *   path="/users/{UserId}",operationId="update_users",summary="update a User",tags={"User"},
     *   @OA\Parameter(
     *      name="UserId",in="path",description="User id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A User",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateUser"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, User::updateRules());
        if (!empty($request->password)) {
            $payload['password'] = bcrypt($request->password);
        }
        if (!empty($request->telefone)) {
            $payload['telefone'] = Phones::format($request->telefone);
        }
        $user = User::where("id", $id)->update($payload);

        return [
            'status' => 200,
            'data' => $user
        ];
    }

    public function recoverPassword(Request $request, $id)
    {
        $payload = $request->all();
        $payload['password'] = bcrypt($request->password);

        User::where("id", $id)->update($payload);

        return response(true);
    }

    /**
     * @OA\Delete(
     *   path="/users/{UserId}",operationId="destroy_users",summary="destroy User",tags={"User"},
     *   @OA\Parameter(
     *      name="UserId",in="path",description="User ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted User",
     *   )
     * )
     */
    public function destroy($id, DeleteUser $deleteUser)
    {
        $user = User::findOrFail($id);

        try {
            DB::beginTransaction();

            if ($user->hasRole('admin-franchise|operator-franchise')) {
                //delete roule
                RoleUser::query()->where('user_id', $user['id'])->delete();
                //delete franchise relation
                UserFranchise::query()->where('user_id', $user['id'])->delete();
            }

            $deleteUser->execute($user);
            event(new UserLoggedOut($user));

            DB::commit();
            return response($user);
        } catch (\Exception $e) {
            DB::rollBack();
            throw  $e;
        }
    }

    public function me()
    {
        $user = auth()->user();
        return (new MeResource($user));
    }

    public function updateUserType(Request $request, $id)
    {
        User::where("id", $id)->update(['tipo' => $request->tipo]);

        return response()->json(['type' => $request->tipo]);
    }
}
