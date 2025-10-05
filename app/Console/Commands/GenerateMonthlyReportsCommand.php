<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Jobs\GenerateMonthlyReportJob;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateMonthlyReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate-monthly 
                            {--store= : Generate report for specific store ID}
                            {--month= : Generate report for specific month (Y-m format)}
                            {--force : Force regeneration even if already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate automated monthly reports for all stores or a specific store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $storeId = $this->option('store');
        $month = $this->option('month');
        $force = $this->option('force');
        
        // Determine report month
        $reportMonth = $month ? Carbon::createFromFormat('Y-m', $month) : now()->subMonth();
        
        $this->info("Generating monthly reports for {$reportMonth->format('F Y')}...");
        
        // Get stores to process
        $stores = $storeId ? 
            Store::where('id', $storeId)->get() : 
            Store::where('status', 'active')->get();
        
        if ($stores->isEmpty()) {
            $this->error('No stores found to process.');
            return 1;
        }
        
        $jobsQueued = 0;
        
        foreach ($stores as $store) {
            // Check if report already exists (unless forced)
            if (!$force && $this->reportExists($store, $reportMonth)) {
                $this->warn("Report for {$store->name} ({$reportMonth->format('Y-m')}) already exists. Use --force to regenerate.");
                continue;
            }
            
            // Queue the report generation job
            GenerateMonthlyReportJob::dispatch($store->id, $reportMonth);
            
            $this->info("✓ Queued monthly report job for: {$store->name}");
            $jobsQueued++;
        }
        
        $this->info("Successfully queued {$jobsQueued} monthly report jobs.");
        
        if ($jobsQueued > 0) {
            $this->info("Reports will be generated in the background and emailed to store owners when ready.");
        }
        
        return 0;
    }
    
    /**
     * Check if monthly report already exists for store and month.
     */
    private function reportExists(Store $store, Carbon $reportMonth): bool
    {
        $fileName = "monthly-report-{$store->id}-{$reportMonth->format('Y-m')}.pdf";
        $filePath = "reports/monthly/{$fileName}";
        
        return \Illuminate\Support\Facades\Storage::exists($filePath);
    }
}
