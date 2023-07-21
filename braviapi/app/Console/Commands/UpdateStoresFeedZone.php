<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Mongo\StoreFeed;
use Packk\Core\Models\Showcase;

class UpdateStoresFeedZone extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'zone:updated {id}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Atualiza as zonas do feed de lojas';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id');

        $stores = StoreFeed::where('zone_id', intval($id))->select('id')->get()->pluck('id')->toArray();
        foreach ($stores as $storeId) {
            dispatch(new SendShopFeedEvent($storeId, 'service_area:update'));
            var_dump('Dispatch Loja #'.$storeId);
        }
        var_dump('Finalizado: '.count($stores). ' Lojas');
        return Command::SUCCESS;
    }
}
