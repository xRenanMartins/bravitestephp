<?php

namespace App\Rules\Order;

use Packk\Core\Models\Schedule;

class GetScheduleStore
{
    const DAY_HOURS =
        ["00:00", "00:30", "01:00", "01:30", "02:00", "02:30", "03:00", "03:30", "04:00", "04:30", "05:00", "05:30", "06:00", "06:30", "07:00", "07:30", "08:00", "08:30", "09:00", "09:30", "10:00", "10:30", "11:00", "11:30", "12:00", "12:30", "13:00", "13:30", "14:00", "14:30", "15:00", "15:30", "16:00", "16:30", "17:00", "17:30", "18:00", "18:30", "19:00", "19:30", "20:00", "20:30", "21:00", "21:30", "22:00", "22:30", "23:00", "23:30"];

    public static function execute($storeId)
    {
        $schedule = Schedule::where('store_id', $storeId)->first();
        if (empty($schedule)) {
            return [];
        }

        $timeArr = str_split($schedule->scheduling_operation);
        $daysWeek = array_chunk($timeArr, 48);
        $days = [];

        foreach ($daysWeek as $key => $day) {
            $days[$key] = self::getOpenHours(implode('', $day));
        }

        return $days;
    }

    private static function getOpenHours(string $hours): array
    {
        $openHours = [];
        $start = 0;
        $hoursOpen = 0;
        $isOpen = false;

        for ($i = 0; $i < strlen($hours); $i++) {
            if ($hours[$i] == '1' && !$isOpen) {
                $start = $i;
                $isOpen = true;
            }
            if ($isOpen) {
                $hoursOpen++;
            }

            if ($hoursOpen == 5) {
                $openHours[] = [$start, $i];
                $start = $i;
                $hoursOpen = 1;
            }

            if ($hours[$i] == '0' && $isOpen) {
                $isOpen = false;
                $hoursOpen = 0;
            }
        }

        $formattedHours = [];
        foreach ($openHours as $item) {
            $start = self::DAY_HOURS[$item[0]];
            $end = self::DAY_HOURS[$item[1]];
            $formattedHours[] = "{$start} - {$end}";
        }

        return $formattedHours;
    }
}