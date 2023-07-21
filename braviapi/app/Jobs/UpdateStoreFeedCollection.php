<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Mongo\StoreFeed;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;

class UpdateStoreFeedCollection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $stores = Store::withoutGlobalScope(DomainScope::class)->select('id')->where('domain_id',1)->get();

        foreach ($stores as $store) {
            dispatch(new SendShopFeedEvent($store->id, 'store.create'));
        }

        $storesFeed = StoreFeed::select('id')->whereNotIn('id', $stores->pluck('id'))->get();
        foreach ($storesFeed as $store) {
            dispatch(new SendShopFeedEvent($store->id, 'store.destroy'));
        }
    }
}
