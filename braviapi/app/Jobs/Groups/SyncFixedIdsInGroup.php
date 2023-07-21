<?php

namespace App\Jobs\Groups;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncFixedIdsInGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private $group, private $function, private array $listIds)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $this->group->{$this->function}()->attach($this->listIds, ['fixed' => 1]);
    }
}
