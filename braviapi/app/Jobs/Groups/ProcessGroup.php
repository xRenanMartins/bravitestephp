<?php

namespace App\Jobs\Groups;

use App\Rules\Group\ProcessCustomerGroup;
use App\Rules\Group\ProcessDeliverymanGroup;
use App\Rules\Group\ProcessStoreGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Models\Group;

class ProcessGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private readonly Group $group)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $class = match ($this->group->type) {
            'E' => new ProcessDeliverymanGroup(),
            'C' => new ProcessCustomerGroup(),
            default => new ProcessStoreGroup(), // L
        };

        $class->execute($this->group);
    }
}
