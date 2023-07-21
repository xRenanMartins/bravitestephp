<?php

use App\Response\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $version = App\Utils\ApplicationVersion::get();
    return "Packk Admin API: {$version}";
});

Route::get("unauthenticated", function () {
    return ApiResponse::sendError("Unauthenticated.", 401);
})->name('unauthenticated');;