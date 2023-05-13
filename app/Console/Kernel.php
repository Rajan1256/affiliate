<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use App\Console\Commands\AutoBonusCron;
use App\Console\Commands\AutoRevenueCron;
use App\Console\Commands\CurrencyCron;
use App\Console\Commands\DailyLeadActivity;
use App\Console\Commands\GeneralLead;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        AutoBonusCron::class,
        AutoRevenueCron::class,
        CurrencyCron::class,
        DailyLeadActivity::class,
        GeneralLead::class, 
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('df:currency')->everyMinute();
        $schedule->command('upload:GeneralLeadInfo')->everyMinute();
        $schedule->command('upload:DailyLeadActivity')->everyMinute();
        $schedule->command('auto:revenue')->everyMinute();
        $schedule->command('auto:bonus')->everyMinute();
    }
    
}
