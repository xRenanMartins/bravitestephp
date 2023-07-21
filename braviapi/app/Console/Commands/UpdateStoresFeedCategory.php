<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Packk\Core\Jobs\SendShopFeedEvent;

class UpdateStoresFeedCategory extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'category:updated {id}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Atualiza todas as lojas do feed que contém essa categoria vinculada';

    /**
     * Execute the console command.
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id');
        $stores = DB::table('categoria_loja')->select('loja_id')->where('categoria_id', $id)
            ->get()->pluck('loja_id')->toArray();

        foreach ($stores as $storeId) {
            dispatch(new SendShopFeedEvent($storeId, 'category.update'));
            var_dump('Dispatch Loja #' . $storeId);
        }

        var_dump('Finalizado: ' . count($stores) . ' Lojas');

        return Command::SUCCESS;
    }
}
