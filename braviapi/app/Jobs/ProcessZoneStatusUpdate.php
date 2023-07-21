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

class ProcessZoneStatusUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private $payload)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        if (!$this->payload['new_status']) {
            $zones = StoreFeed::select('id')->where('zone_id', intval($this->payload['zone_id']))->get();
            foreach ($zones as $zoneItem) {
                dispatch(new SendShopFeedEvent($zoneItem->id, 'service_area:update', ['zone_id' => $this->payload['new_zone_id']]));
            }
        } else {
            $zones = StoreFeed::select('id')->whereNull('zone_id')->get();
            foreach ($zones as $zoneItem) {
                dispatch(new SendShopFeedEvent($zoneItem->id, 'service_area:update'));
            }
        }
    }
}
