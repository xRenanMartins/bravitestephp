<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Showcase;
use Packk\Core\Scopes\DomainScope;

class CreateShowcaseFeedInMongo extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'showcase:create {id?}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Cria todas ou uma vitrine do domínio da Americanas no mongoDB';

    /**
     * Execute the console command.
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id');
        $query = Showcase::withoutGlobalScope(DomainScope::class)->select('id')->where('domain_id',1);
        if (!empty($id)) {
            $query->where('id',$id);
        }

        $showcases = $query->get();

        foreach ($showcases as $showcase) {
            dispatch(new SendShowcaseFeedEvent($showcase->id, 'showcase.create'));
            var_dump('Dispatch vitrine #'.$showcase->id);
        }

        var_dump('Finalizado: '.count($showcases). ' vitrines');
        return 0;
    }
}
