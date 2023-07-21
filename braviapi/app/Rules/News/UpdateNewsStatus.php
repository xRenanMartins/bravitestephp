<?php

namespace App\Rules\News;

use App\Utils\Files;
use Carbon\Carbon;
use Packk\Core\Models\LogTable;
use Packk\Core\Models\News;

class UpdateNewsStatus
{
    public function execute($id, $pause)
    {
        $news = News::findOrFail($id);
        $newStatus = $pause ? 'PAUSED' : 'ACTIVE';

        LogTable::log('UPDATE', "novidades", $news->id, 'status', $news->status, $newStatus);

        $news->status = $newStatus;
        $news->save();
    }
}