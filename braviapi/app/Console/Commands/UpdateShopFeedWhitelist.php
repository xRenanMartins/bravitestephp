<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;

class UpdateShopFeedWhitelist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopFeed:update.whitelist {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza o objeto whitelist';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id');

        $query = Store::withoutGlobalScope(DomainScope::class)->select('id')->where('domain_id',1);
        if (!empty($id)) {
            $query->where('id',$id);
        }

        $stores = $query->get();
        foreach ($stores as $store) {
            dispatch(new SendShopFeedEvent($store->id, 'store:whitelist:update'));
            var_dump('Dispatch Loja #'.$store->id);
        }

        var_dump('Finalizado: '.count($stores). ' Lojas');
        return 0;
    }
}
