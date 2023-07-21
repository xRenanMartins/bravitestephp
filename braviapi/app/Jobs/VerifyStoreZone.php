<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Models\Store;

class VerifyStoreZone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private Store $store)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $addresses = $this->store->addresses()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNull('zone_id')->get();

        foreach ($addresses as $address) {
            try {
                $validateZone = $this->store->validateStoreInsideZone($address);

                if ($validateZone['success']) {
                    $address->zone_id = $validateZone['id'];
                    $address->save();
                }
            } catch (\Exception $e) {
                app('sentry')->captureException($e);
            }
        }
    }
}
