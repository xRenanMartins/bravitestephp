<?php

namespace App\Rules\Setting;

use App\Exceptions\GenericException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\Store;

class InsertMultipleDeliveryTax
{
    private array $payloadList = [];

    public function execute(array $payload)
    {
        try {
            DB::beginTransaction();
            unset($payload['file']);

            $stores = Store::select('id')->whereIn('id', $payload['ids'])->get();
            throw_if($stores->isEmpty(), new GenericException('Os IDs enviados são inválidos.'));

            $payload['created_at'] = Carbon::today()->format('d/m/Y H:i');
            $payload['id'] = Str::uuid();

            $ids = array_chunk($stores->pluck('id')->toArray(), 3000);
            $allStores = [];

            foreach ($ids as $listIds) {
                $payload['ids'] = $listIds;

                LogTable::log(
                    'CREATE',
                    'delivery_tax:multiple',
                    0,
                    'many',
                    'Frete em massa',
                    json_encode($payload)
                );

                $this->payloadList[] = $payload;
                $allStores = array_merge($allStores, $listIds);
            }
            $payload['ids'] = $allStores;
            DB::commit();
            $this->sendJobs();
            return $payload;
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    private function sendJobs(): void
    {
        foreach ($this->payloadList as $payload) {
            dispatch(new \App\Jobs\InsertMultipleDeliveryTax($payload));
        }
    }
}