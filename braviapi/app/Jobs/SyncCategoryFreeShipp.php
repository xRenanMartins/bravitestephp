<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Category;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Traits\Delayable;
use Packk\Core\Traits\Loggable;

class SyncCategoryFreeShipp implements ShouldQueue
{
    use Dispatchable,Loggable, InteractsWithQueue, Queueable, SerializesModels, Delayable {
        Delayable::delay insteadof Queueable;
    }

    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct(private Store $store)
    {
        //
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $remain = $this->remain();
        if ($remain > 0) {
            $this->release($remain);
            return;
        }

        $categories = Category::withoutGlobalScope(DomainScope::class)
            ->where('domain_id', $this->store->domain_id)
            ->where('showcase_segment', 'FRETE_GRATIS')->get();

        Cache::forget("store.{$this->store->id}.settings");
        $value = $this->store->getSetting("discount_delivery");

        foreach ($categories as $category) {
            if (is_null($value) || $value > 0) {
                $category->stores()->detach($this->store->id);
            } else {
                $category->stores()->syncWithoutDetaching($this->store->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
