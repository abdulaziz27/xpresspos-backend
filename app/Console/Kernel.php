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
        // Generate monthly reports on the 1st of each month at 6 AM
        $schedule->command('reports:generate-monthly')
            ->monthlyOn(1, '06:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Send payment reminders for subscriptions expiring in 7 days
        $schedule->command('subscription:send-payment-reminders --days=7')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Send urgent payment reminders for subscriptions expiring in 3 days
        $schedule->command('subscription:send-payment-reminders --days=3')
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Send final payment reminders for subscriptions expiring in 1 day
        $schedule->command('subscription:send-payment-reminders --days=1')
            ->dailyAt('11:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Process failed payment retry notifications (daily at 2 PM)
        $schedule->command('subscription:process-failed-payments --retry')
            ->dailyAt('14:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Process subscription suspensions for repeatedly failed payments (daily at 3 PM)
        $schedule->command('subscription:process-failed-payments --suspend')
            ->dailyAt('15:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Backup cleanup - remove old report files (older than 6 months)
        $schedule->call(function () {
            $this->cleanupOldReports();
        })->monthly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Clean up old report files.
     */
    private function cleanupOldReports(): void
    {
        $storage = \Illuminate\Support\Facades\Storage::disk('local');
        $cutoffDate = now()->subMonths(6);
        
        $files = $storage->files('reports/monthly');
        
        foreach ($files as $file) {
            $fileDate = $storage->lastModified($file);
            
            if ($fileDate && \Carbon\Carbon::createFromTimestamp($fileDate)->lt($cutoffDate)) {
                $storage->delete($file);
                logger()->info('Deleted old monthly report file', ['file' => $file]);
            }
        }
    }
}
