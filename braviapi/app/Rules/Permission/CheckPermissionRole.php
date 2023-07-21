<?php

namespace App\Rules\Permission;

use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\PermissionRolesChilds;
use Packk\Core\Models\Permission;

class CheckPermissionRole
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function execute($routeActionName, $roles)
    {
        if ($this->checkPrivilege()) {
            return true;
        }

        $authorized = false;
        $permission = $this->getPermission($routeActionName, 'packkadmin');
        if ($permission) {
            $permissionRoles = $permission->permission_roles()->get()->pluck('role_id')->toArray();
            $myRolesChilds = $this->getRolesChilds($roles);

            foreach ($myRolesChilds as $myRole) {
                if (in_array($myRole, $permissionRoles)) {
                    $authorized = true;
                    break;
                }
            }
        }

        return $authorized;
    }

    private function getPermission($routeActionName, $key_system)
    {
        $key = str_replace('App\Http\Controllers\\', '', $routeActionName);

        $permission = Permission::where('key', 'Rest\Admin\\' . $key)->where('key_system', $key_system)->first();
        return $permission;
    }

    public function getRolesChilds($rolesIds)
    {
        return Cache::remember("user.{$this->user->id}.roles", 21600, function () use ($rolesIds) {
            $ids = $rolesIds;

            foreach ($rolesIds as $roleId) {
                $permissionChildren = PermissionRolesChilds::with('childrenRecursive')
                    ->where('role_id', $roleId)->get()->toArray();

                foreach ($permissionChildren as $children) {
                    if (count($children['children_recursive']) > 0) {
                        $temp = self::getChildrenRecursive($children['children_recursive']);
                        $ids = array_merge($ids, $temp);
                    }
                    $ids[] = $children['role_child_id'];
                }
            }

            return $ids;
        });
    }

    private static function getChildrenRecursive($permissionChildren)
    {

        $ids = [];
        foreach ($permissionChildren as $children) {
            if (count($children['children_recursive']) > 0) {
                $temp = self::getChildrenRecursive($children['children_recursive']);
                $ids = array_merge($ids, $temp);
            }
            $ids[] = $children['role_child_id'];
        }
        return $ids;
    }

    public function checkPrivilege()
    {
        return $this->user->hasRole('owner|master|admin-all');
    }
}