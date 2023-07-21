<?php

namespace App\Jobs\Vouchers;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LinkVoucherStore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 0;
    public $tries = 1;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private $voucher, private $payload)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $arrayStores = $this->payload['stores'] ?? [];
        $this->voucher->stores()->attach($arrayStores, ['blacklist' => $this->payload['blacklist']]);
    }
}
