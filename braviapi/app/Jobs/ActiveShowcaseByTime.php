<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Actions\Admin\ShowcaseFeed\HandleShowcaseFeed;
use Packk\Core\Jobs\SendShowcaseFeedEvent;

class ActiveShowcaseByTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $showcases = \Packk\Core\Models\Showcase::query()->select('id')->where('ativo', true)->where('domain_id', 1)->get();
        foreach($showcases as $showcase) {
            dispatch(new SendShowcaseFeedEvent($showcase->id, 'showcase.update', ['ativo']));
        }
    }
}
