<?php

namespace App\Jobs;

use App\Rules\Customer\InsertLogCredit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class InsertMultipleCredits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 0;
    public $tries = 1;
    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private readonly array $payload)
    {
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        foreach ($this->payload['customers'] as $id) {
            try {
                DB::beginTransaction();
                (new InsertLogCredit())->execute([
                    'customer_id' => $id,
                    'expire' => $this->payload['expire_in'] ?? null,
                    'value' => $this->payload['value'] * 100,
                    'reason' => $this->payload['reason'],
                ]);
                DB::commit();
            } catch (\Exception $ex) {
                DB::rollBack();
                app('sentry')->captureException($ex);
            }
        }
    }
}
