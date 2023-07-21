<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Packk\Core\Models\Store;
use Packk\Core\Scopes\DomainScope;
use Packk\Core\Jobs\SendShopFeedEvent;

class UpdateShopFeedCategory extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'shopFeed:update.category {id?}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Atualiza as categorias das lojas';

    /**
     * Execute the console command.
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
            dispatch(new SendShopFeedEvent($store->id, 'category.update'));
            var_dump('Dispatch Loja #'.$store->id);
        }

        var_dump('Finalizado: '.count($stores). ' Lojas');
        return 0;
    }
}
