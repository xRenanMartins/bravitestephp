<?php

namespace App\Response;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function sendResponse($data = [], $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data
        ], $status);
    }

    public static function sendError($message, $status = 400, $details = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($details) {
            $response['details'] = $details;
        }

        return response()->json($response, $status);
    }

    public static function sendUnexpectedError(\Exception $exception, $status = 500): JsonResponse
    {
//        if (!isLocalEnv()) {
//            app('sentry')->captureException($exception);
//        }

        return response()->json([
            'success' => false,
            'message' => 'Ocorreu uma falha inesperada ao realizar a ação solicitada. Tente novamente!',
            'details' => [
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ]
        ], $status);
    }
}