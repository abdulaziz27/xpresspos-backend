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
