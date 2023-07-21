<?php

namespace App\Http\Controllers;

use App\Jobs\ResetUsersCache;
use App\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Models\PermissionRoles;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        return Permission::like('key', $request->key)
            ->like('description', $request->description)
            ->identic('key_system', $request->plataform)
            ->when($user->hasPermission('create_only_delivery_permissions', false), function ($q) {
                $q->whereIn('key_system', ['iss', 'deliveryman_operation']);
            })
            ->orderBy('description')
            ->with('permission_roles.role')
            ->simplePaginate($request->length);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'key' => 'required',
            'description' => 'required',
            'roles' => 'required',
            'key_system' => 'required'
        ]);

        $roles = $payload['roles'];
        unset($payload['roles']);

        $data = Permission::create($payload);
        $data->rolesObj()->sync($roles);
        dispatch(new ResetUsersCache($roles));

        return response([
            'success' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $request->validate([
            'key' => 'required',
            'description' => 'required',
            'roles' => 'required',
            'key_system' => 'required'
        ]);

        $roles = $payload['roles'];
        unset($payload['roles']);

        $data = Permission::find($id);
        $data->update($payload);

        $removedRoles = DB::table('permission_roles')->where('permission_id', $id)
            ->whereNotIn('role_id', $roles)->get()->pluck('role_id');

        $data->rolesObj()->sync($roles);
        dispatch(new ResetUsersCache($roles));
        dispatch(new ResetUsersCache($removedRoles));

        return ApiResponse::sendResponse($data);
    }

    public function destroy($id)
    {
        $menu = Permission::find($id);

        $roles = PermissionRoles::query()->where('permission_id', $id)->get()->pluck('role_id');
        dispatch(new ResetUsersCache($roles));

        PermissionRoles::query()->where('permission_id', $id)->delete();
        $menu->delete();

        return response(['success' => true,]);
    }


}