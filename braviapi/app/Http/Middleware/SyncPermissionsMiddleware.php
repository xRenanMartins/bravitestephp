<?php

namespace App\Http\Middleware;

use App\Traits\PermissionObjectTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SyncPermissionsMiddleware
{
    use PermissionObjectTrait;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $permissionVersion = $request->header('permission-key');
        if (!empty($permissionVersion)) {
            $user = $request->user();

            if ($permissionVersion !== Cache::get("user.{$user->id}.permission.key")) {
                $response->setData([
                    'original' => $response->getData(),
                    'permissions' => self::getPermissionCache($user)
                ]);
            }
        }

        return $response;
    }
}
