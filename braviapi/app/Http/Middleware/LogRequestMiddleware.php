<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Packk\Core\Util\Rabbitmq;

class LogRequestMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!self::isProd()) {
            return $next($request);
        }

        $executionTime = now();
        $response = $next($request);

        $user = Auth::user();

        $rabbit = new Rabbitmq('admin', 'rkey-admin');
        $rabbit->publish([
            'env' => env('APP_ENV', 'dev'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'controller' => $request->route()->getAction()['controller'] ?? null,
            'status_code' => $response->status(),
            'time' => now()->diffInMilliseconds($executionTime).'ms',
            'domain_id' => (int)currentDomain(),
            'user' => !empty($user) ? [
                'id' => $user->id,
                'email'=> $user->email,
                'roles'=> $user->dbRoles()->pluck('label'),
            ] : [],
            'payload' => json_encode($request->all()),
            'response' => json_encode($response->getOriginalContent()),
        ]);

        return $response;
    }

    private static function isProd(): bool
    {
        return (in_array(env('APP_ENV', 'dev'), ['staging', 'production']));
    }
}
