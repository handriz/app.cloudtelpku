<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        // GUNAKAN NAMA CLASS YANG BENAR INI:
        \App\Console\Commands\FreshWithExceptions::class,
    ];
    
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('queue:heartbeat')->everyMinute();
        $schedule->command('supervisor:status-check')->everyMinute();
        $schedule->command('app:clean-temp-photos')->daily();
        $schedule->command('photos:process-inbox --limit=200')
             ->everyFiveMinutes()
                ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
