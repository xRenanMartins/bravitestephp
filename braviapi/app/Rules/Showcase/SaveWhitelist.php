<?php

namespace App\Rules\Showcase;

use Illuminate\Support\Facades\DB;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Customer;
use Packk\Core\Models\Whitelist;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Models\Store;

class SaveWhitelist
{
    public function execute($showcase, $payload)
    {
        $clients = collect([]);
        if (!empty($payload['clients_id'])) {
            $lists = explode(',', str_replace(" ", '', str_replace("\n", '', $payload['clients_id'])));

            if(!empty($payload['store_id'])){
                $clients = Customer::withoutGlobalScope(DomainScope::class)->select('id')
                ->whereIn('id', $lists)->where('domain_id', $showcase->domain_id)->get();
                throw_if($clients->isEmpty(), new \Exception('Os clientes informados não pertencem ao domínio dessa loja.'));
            }else{
                $clients = Customer::withoutGlobalScope(DomainScope::class)->select('id')
                ->whereIn('id', $lists)->where('domain_id', $showcase->domain_id)->get();
                throw_if($clients->isEmpty(), new \Exception('Os clientes informados não pertencem ao domínio dessa vitrine.'));
            }
        }

        if (!empty($payload['rfm'])) {
            $clientsRfm = Customer::withoutGlobalScope(DomainScope::class)
                ->select('id')
                ->where('customer_group_id', $payload['rfm'])
                ->where('domain_id', $showcase->domain_id)->get();
            $clients = $clients->merge($clientsRfm);
        }

        $add = [];
        $where = 'showcase_id';
        $type = 'SHOWCASE';
        if(!empty($payload['store_id'])){
            $where = 'store_id';
            $type = 'STORE';
            foreach ($clients as $client) {
                $add[] = [
                    "customer_id" => $client->id,
                    "type" => $type,
                    "status" => 1,
                    "domain_id" => $showcase->domain_id,
                    "store_id" => $showcase->id,
                    "showcase_id" => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }else{
            foreach ($clients as $client) {
                $add[] = [
                    "customer_id" => $client->id,
                    "type" => $type,
                    "status" => 1,
                    "domain_id" => $showcase->domain_id,
                    "showcase_id" => $showcase->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        try {
            DB::beginTransaction();
            Whitelist::query()
                ->whereIn('customer_id', $clients->pluck('id')->toArray())
                ->where($where, $showcase->id)
                ->where('type', $type)
                ->forceDelete();

            DB::table('whitelists')->insert($add);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        if ($showcase instanceof Store) {
            dispatch(new SendShopFeedEvent($showcase->id, 'store:whitelist:update'));
        }

        return ['success' => true];

    }
}