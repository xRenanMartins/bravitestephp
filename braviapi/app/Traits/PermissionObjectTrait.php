<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\User;

trait PermissionObjectTrait
{
    public static function getPermissionCache($user)
    {
        return [
            'items' => Cache::rememberForever("user.{$user->id}.permission.items", function () use($user) {
                $permissions = $user->getPermissionNames();
                if ($user->hasRole(User::ROLE_ADMIN_PRIVILEGES)) {
                    $permissions[] = 'master';
                }
                return $permissions;
            }),
            'key' => Cache::rememberForever("user.{$user->id}.permission.key", function () {
                return md5(now());
            })
        ];
    }
}