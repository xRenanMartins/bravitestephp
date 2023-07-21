<?php

namespace App\Rules\News;

use App\Utils\Files;
use Carbon\Carbon;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\News;

class ExpireNews
{
    public function execute($id)
    {
        $news = News::findOrFail($id);
        $news->disable_on = \Carbon\Carbon::now()->subMinutes(5);
        $news->save();
    }
}