<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Activity;
use Exception;

class OnlineController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function online(Request $request)
    {
        try {
            $user = $request->user();

            $context = [
                '[::operador]' => $user->nome_completo,
            ];
            $user->addAtividade('OPERATOR_ONLINE', $context, $user->id, 'USER');
            return response([], 200);
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function offline(Request $request)
    {
        try {
            $user = $request->user();

            $context = [
                '[::operador]' => $user->nome_completo,
            ];
            $user->addAtividade('OPERATOR_OFFLINE', $context, $user->id, 'USER');
            return response([], 200);
        } catch (Exception $e) {
            return $e;
        }
    }
}
