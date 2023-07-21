<?php

namespace App\Jobs;

use App\Rules\Setting\SetStoreSetting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Setting;
use Packk\Core\Models\Store;
use Packk\Core\Util\Formatter;

class InsertMultipleDeliveryTax implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 0;
    public $tries = 1;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private array $payload)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $insert = [];
        $now = now();
        $expire = Carbon::createFromFormat('Y-m-d H:i:s', $this->payload['expire_in']);
        $value = intval($this->payload['value'] * 100);
        $title = 'Remover frete em massa de R$' . Formatter::money($value);

        try {
            $stores = Store::whereIn('id', $this->payload['ids'])->get();
            foreach ($stores as $store) {
                SetStoreSetting::execute($store, 'discount_delivery', $value);

                $insert[] = [
                    'description' => $title,
                    'expected_on' => $expire,
                    'coluna' => 'delivery_tax',
                    'novo_valor' => 0,
                    'loja_id' => $store->id,
                    'domain_id' => $store->domain_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('acoes_agendadas')->insert($insert);
        } catch (\Exception $ex) {
            app('sentry')->captureException($ex);
        }
    }
}
