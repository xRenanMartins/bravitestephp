<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Psr\Log\LogLevel;
use Throwable;
use Packk\Core\Exceptions\RuleException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        \League\OAuth2\Server\Exception\OAuthServerException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $exception) {
            if(extension_loaded('newrelic')) {
                if($exception instanceof RuleException || $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    try {
                        newrelic_set_appname(env('ENV_NAME'));
                        newrelic_notice_error(null, $exception);
                    }
                    catch(Throwable $e){}
                }
            }

            if (app()->bound('sentry')) {
                app('sentry')->captureException($exception);
            }
        });
    }
}
