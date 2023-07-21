<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Actions\Customer\Signature\GenerateOrderSignature;

class GenerateOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private $id, private $domain_id)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        if(config('globals.dynamic.domain') != "{$this->domain_id}") {
            config(['globals.dynamic.domain' => "{$this->domain_id}"]);
        }

        $generateSignature = new GenerateOrderSignature();
        $generateSignature->execute($this->id);
    }
}
