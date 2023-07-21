<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        if (isset($request->start) and isset($request->length)) {
            $total = $request->start / $request->length;
            $page = ($total + 1) > 0 ? ceil($total) + 1 : 1;

            $request->merge([
                'page' => $page
            ]);

            return Role::query()
                ->identic('name', $request->name)
                ->orLike('label', $request->name)
                ->with('permission_roles_childs')
                ->paginate($request->length);
        } else {
            return Role::orderBy('label', 'asc')->get();
        }

    }

    public function store(Request $request)
    {
        $value = [
            'name' => $request->name,
            'label' => $request->label
        ];
        Role::insert($value);
        return [true];
    }

    public function update(Request $request, $id)
    {
        $value = [
            'label' => $request->label
        ];
        Role::where('id', $id)->update($value);
        return [true];
    }

    public function childs(Request $request, $id)
    {
        $payload = $request->validate([
            'roles' => 'required',
        ]);

        $data = Role::find($id);
        $data->permissionRolesChildsObj()->sync($payload['roles']);

        return response([
            'success' => true,
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        if (DB::table('permission_roles')->where('role_id', $id)->exists()
            || DB::table('permission_roles_childs')->where('role_id', $id)->exists()) {
            throw new \Exception("Não é possível excluir este perfil, pois está vinculado a uma permissão.");
        } else if (DB::table('menu_roles')->where('role_id', $id)->exists()) {
            throw new \Exception("Não é possível excluir este perfil, pois está vinculado a um menu.");
        } else if (DB::table('role_user')->join('users', 'users.id', 'role_user.user_id')->where('role_user.role_id', $id)->whereNull('users.deleted_at')->exists()) {
            throw new \Exception("Não é possível excluir este perfil, pois está vinculado a um usuário");
        }

        Role::where('id', $id)->delete();
        return response()->json(["success" => true]);
    }
}