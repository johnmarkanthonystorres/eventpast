<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send event reminders at strategic times
        
        // Check for 7-day reminders every day at 9 AM
        $schedule->command('reminders:send --type=7day')
            ->dailyAt('09:00')
            ->timezone('UTC');
        
        // Check for 1-day reminders every day at 9 AM
        $schedule->command('reminders:send --type=1day')
            ->dailyAt('09:00')
            ->timezone('UTC');
        
        // Check for 2-hour reminders every hour (to catch events starting at different times)
        $schedule->command('reminders:send --type=2hour')
            ->hourly();
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
