<?php

namespace App\Http\Controllers;

class Responser
{
    const OK    		   = 200;
    const SERVER_ERROR     = 500;
    const GONE_ERROR       = 410;
    const FORBIDEN_ERROR   = 403;
    const NOT_FOUND_ERROR  = 404;
    const PRECONDITION_FAILED = 412;

    public static function response($data,$code)
    {
        return response($data,$code)->header('Content-Type', 'json/text');
    }
}
