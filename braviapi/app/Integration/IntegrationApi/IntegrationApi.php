<?php

namespace App\Integration\IntegrationApi;

use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Decorators\ClientDecorator;
use Packk\Core\Models\OAuthClient;

class IntegrationApi
{
    private $gclient;
    private string $baseUrl;
    private $clientId;

    public function __construct()
    {
        $this->gclient = new ClientDecorator();
        $this->baseUrl = env('INTEGRATION_API_URL', "https://merchant.guaxinim.packk.com.br");
        $this->clientId = env('INTEGRATION_API_ID', 60);
    }

    private function auth($domainId)
    {
        $validateCache = Carbon::now();
        $validateCache->addHours(24);

        return Cache::remember("integration_api_token_{$domainId}", $validateCache, function () use ($domainId) {
            $oauth = OAuthClient::query()->findOrFail($this->clientId); //

            $result = $this->gclient->postRequest("{$this->baseUrl}/oauth/token", [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $oauth->id,
                    'client_secret' => $oauth->secret,
                ],
                'headers' => [
                    "Content-Type" => "application/json",
                    "domain-id" => $domainId,
                ],
            ]);
            $response = json_decode($result->getBody()->getContents());

            return $response->access_token;
        });
    }

    public function sendRequest($method, $path, $domainId, $body = [])
    {
        try {
            $token = $this->auth($domainId);

            $method .= "Request";
            $result = $this->gclient->$method("{$this->baseUrl}/{$path}", [
                'json' => $body,
                'headers' => [
                    "Content-Type" => "application/json",
                    "Authorization" => 'Bearer ' . $token ?? null,
                    "domain-id" => $domainId,
                ],
            ]);

            return json_decode($result->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }
}
