<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateTrait
{
    public static function getFormattedDate(string $date, string $format = 'Y-m-d H:i:s'): string
    {
        try {
            $date = Carbon::createfromformat('d/m/Y H:i:s', $date)->format($format);
        } catch (\Exception) {
            $date = Carbon::createfromformat('d/m/Y H:i', $date)->format($format);
        }

        return $date;
    }
}