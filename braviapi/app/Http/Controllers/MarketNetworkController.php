<?php

namespace App\Http\Controllers;

use App\Response\ApiResponse;
use Illuminate\Http\Request;
use Packk\Core\Gateways\AmericanasMarket\AmericanasMarketGateway;

class MarketNetworkController extends Controller
{
    public function autocomplete(Request $request)
    {
        try {
            $gateway = new AmericanasMarketGateway();
            $response = $gateway->service("capacity")->resource("store-manager")->listNetworks($request->query('search', ''));
            return ApiResponse::sendResponse($response['data'] ?? []);
        } catch (\Exception $e) {
            return ApiResponse::sendUnexpectedError($e);
        }
    }
}
