<?php

namespace App\Integration\IntegrationApi\Services;

use App\Integration\IntegrationApi\IntegrationApi;
use Packk\Core\Models\Store as StoreModel;

class Store extends IntegrationApi
{
    public function getStoreIntegrations(StoreModel $store)
    {
        $response = $this->sendRequest('get', "api/v1/integrations/stores/{$store->id}", $store->domain_id);
        return $response ?? [];
    }

    public function createStoreIntegration(StoreModel $store, $integration, $version = '1')
    {
        $response = $this->sendRequest('post', "api/v1/integrations/stores/{$store->id}/create?app={$integration}&version={$version}", $store->domain_id);
        return $response;
    }

    public function editStoreIntegration(StoreModel $store, $integration, $payload)
    {
        $response = $this->sendRequest('put', "api/v1/integrations/stores/{$store->id}/update?app={$integration}", $store->domain_id, $payload);
        return $response ?? [];
    }

    public function changeStoreIntegration(StoreModel $store, $integration, $action)
    {
        $response = $this->sendRequest('put', "api/v1/integrations/stores/{$store->id}/status?app={$integration}&is_active={$action}", $store->domain_id);
        return $response ?? [];
    }

    public function deleteStoreIntegration(StoreModel $store, $integration, $version = '1')
    {
        $response = $this->sendRequest('delete', "api/v1/integrations/stores/{$store->id}/delete?app={$integration}&version={$version}", $store->domain_id);
        return $response;
    }
}
