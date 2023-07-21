<?php

namespace App\Rules\Customer;

use App\Exceptions\GenericException;
use App\Jobs\InsertMultipleCredits;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packk\Core\Models\Customer;
use Packk\Core\Models\LogTable;

class InsertMultipleLogCredit
{
    private array $payloadList = [];

    public function execute(array $payload)
    {
        try {
            DB::beginTransaction();
            unset($payload['file']);

            $customers = Customer::select('id')->whereIn('id', $payload['customers'])->get();
            throw_if($customers->isEmpty(), new GenericException('Os IDs enviados são inválidos.'));

            $payload['reason'] = empty($payload['reason']) ? 'CRÉDITO_EM_MASSA' : $payload['reason'];
            $payload['created_at'] = Carbon::today()->format('d/m/Y H:i');
            $payload['id'] = Str::uuid();

            $ids = array_chunk($customers->pluck('id')->toArray(), 3000);
            $allCustomers = [];

            foreach ($ids as $customers) {
                $payload['customers'] = $customers;

                LogTable::log(
                    'CREATE',
                    'log_credits:multiple',
                    0,
                    'many',
                    'Crédito em massa: ' . $payload['reason'],
                    json_encode($payload)
                );
                $this->payloadList[] = $payload;
                $allCustomers = array_merge($allCustomers, $customers);
            }

            $payload['customers'] = $allCustomers;
            DB::commit();
            $this->dispatchCredits();

            return $payload;
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    private function dispatchCredits(): void
    {
        foreach ($this->payloadList as $payload) {
            dispatch(new InsertMultipleCredits($payload));
        }
    }
}