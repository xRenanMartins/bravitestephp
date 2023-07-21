<?php

namespace App\Console;

use App\Jobs\ActiveShowcaseByTime;
use App\Jobs\Invoice\ChargeInvoice;
use App\Jobs\Groups\ProcessGroups;
use App\Jobs\RunScheduledActions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $app_mode = env('APP_MODE', 'API');
        if ($app_mode == 'STAGING_WORKER') {
            $this->runScheduleStaging($schedule);
        }

        if ($app_mode == 'WORKER') {
            $this->runScheduleProduction($schedule);
        }
    }

    private function runScheduleStaging(Schedule $schedule)
    {
        // $schedule->command('example')->hourly();
    }

    private function runScheduleProduction(Schedule $schedule)
    {
        $schedule->call(function () {
            dispatch(new ActiveShowcaseByTime());
        })->cron('0,30 * * * *');

        $schedule->call(function () {
            dispatch(new RunScheduledActions('process'));
        })->everyFiveMinutes();

        $schedule->call(function () {
            dispatch(new RunScheduledActions('clear'));
        })->weekly();

        $schedule->call(function () {
            dispatch(new ProcessGroups());
        })->dailyAt('02:00');

        //invoices
        $schedule->call(function () {
            dispatch(new ChargeInvoice('DELETE_INVOICES'));
        })->cron("0 1 1 * *");

        $schedule->call(function () {
            dispatch(new ChargeInvoice('CREATE_INVOICES'));
        })->cron("0 2 26 * *");
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
