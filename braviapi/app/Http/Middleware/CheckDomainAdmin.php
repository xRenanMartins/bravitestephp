<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Packk\Core\Traits\AuthBearer;
use Closure;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckDomainAdmin
{
    use AuthBearer;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $this->LogToIn($request->bearerToken(), false);

        if (isset($user)) {
            $request->headers->set('x-admin', "1");
            $request->headers->set('domain-id', $request->get('domain_id'));

            $is_change_password = str_contains(request()->path(), 'change_password');
            if ($user->password_temporario && !$is_change_password) {
                throw new HttpException(401, "É necessario resetar a sua senha, deslogue e logue novamente.", null, [], 4003);
            }
        }
        return $next($request);
    }
}

