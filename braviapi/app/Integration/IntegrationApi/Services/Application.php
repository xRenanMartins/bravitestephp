<?php

namespace App\Integration\IntegrationApi\Services;

use App\Integration\IntegrationApi\IntegrationApi;

class Application extends IntegrationApi
{
    public function listApps($domainId)
    {
        $response = $this->sendRequest('get', "api/v1/integrations/apps/domains/{$domainId}", $domainId);
        return $response->data ?? [];
    }
}
