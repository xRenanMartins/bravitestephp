<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Packk\Core\Actions\Admin\Permissions\CheckPermissionRole;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckPermissionRoles
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        $roles = $user->rolesUser->pluck('role_id')->all();
        $routeActionName = $request->route()->getActionName();

        $check = new CheckPermissionRole($user);
        if ($check->execute($routeActionName, $roles, 'packkadmin')) {
            return $next($request);
        } else {
            throw new HttpException(403, 'Você não tem permissão para acessar essa página.');
        }
    }
}
