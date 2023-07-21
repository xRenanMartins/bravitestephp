<?php

namespace App\Rules\Customer;

use Carbon\Carbon;
use Packk\Core\Models\Customer;

class InsertLogCredit
{
    public function execute(array $payload)
    {
        $customer = Customer::findOrFail($payload['customer_id']);
        $date = null;
        if (!empty($payload['expire'])) {
            try {
                $date = Carbon::createFromFormat('Y-m-d H:i:s', $payload['expire']);
            } catch (\Exception) {
                $date = Carbon::createFromFormat('d/m/Y H:i:s', $payload['expire']);
            }
        }

        $reason = $payload['reason'] ?? 'PAINEL_ADMIN';
        $customer->add_credits($payload['value'], $reason, null, $date);
    }
}