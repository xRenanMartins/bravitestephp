<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Models\ScheduledAction;

class RunScheduledActions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private string $action)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        if ($this->action === 'process') {
            ScheduledAction::process();
        } elseif ($this->action == 'clear') {
            ScheduledAction::where('estado', 'FINALIZADO')->where('coluna', 'delivery_tax')->delete();
        }
    }
}
