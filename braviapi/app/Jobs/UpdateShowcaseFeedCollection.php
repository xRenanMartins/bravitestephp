<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Mongo\ShowcaseFeed;
use Packk\Core\Models\Showcase;
use Packk\Core\Scopes\DomainScope;

class UpdateShowcaseFeedCollection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $showcases = Showcase::withoutGlobalScope(DomainScope::class)->select('id')->where('domain_id',1)->get();
        foreach ($showcases as $showcase) {
            dispatch(new SendShowcaseFeedEvent($showcase->id, 'showcase.create'));
        }

        $showcasesFeed = ShowcaseFeed::select('id')->whereNotIn('id', $showcases->pluck('id'))->get();
        foreach ($showcasesFeed as $showcase) {
            dispatch(new SendShowcaseFeedEvent($showcase->id, 'showcase.destroy'));
        }
    }
}
