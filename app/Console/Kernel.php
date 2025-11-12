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
        // 1. Command bawaan tetap setiap menit
        $schedule->command('queue:heartbeat')->everyMinute();
        $schedule->command('supervisor:status-check')->everyMinute();
       
        // 2. Perbaikan sintaks photos:process-inbox
        $schedule->command('photos:process-inbox --limit=200')
        ->everyFiveMinutes()
        ->withoutOverlapping();

        // 3. Command clean-temp-photos
         $schedule->command('app:clean-temporary-photos')->daily();
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
