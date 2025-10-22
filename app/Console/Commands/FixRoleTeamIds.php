<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixRoleTeamIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:role-team-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix role assignments that have NULL store_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Fixing role team IDs...');
        
        // Find role assignments with NULL store_id
        $assignments = \DB::table('model_has_roles')
            ->whereNull('store_id')
            ->get();
            
        if ($assignments->isEmpty()) {
            $this->info('✅ No role assignments need fixing!');
            return 0;
        }
        
        $this->info("Found {$assignments->count()} role assignments with NULL store_id");
        
        $fixed = 0;
        
        foreach ($assignments as $assignment) {
            try {
                // Get user and role
                $user = \App\Models\User::find($assignment->model_id);
                $role = \Spatie\Permission\Models\Role::find($assignment->role_id);
                
                if (!$user || !$role) {
                    continue;
                }
                
                // Determine correct store_id
                $storeId = null;
                if ($role->store_id) {
                    // Store-specific role, use role's store_id
                    $storeId = $role->store_id;
                } elseif ($user->store_id) {
                    // Global role but user has store, use user's store_id
                    $storeId = $user->store_id;
                }
                
                if ($storeId) {
                    // Update store_id
                    \DB::table('model_has_roles')
                        ->where('role_id', $assignment->role_id)
                        ->where('model_type', $assignment->model_type)
                        ->where('model_id', $assignment->model_id)
                        ->update(['store_id' => $storeId]);
                    
                    $fixed++;
                    $this->line("✅ Fixed: {$user->email} -> {$role->name} (store: {$storeId})");
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to fix assignment: {$e->getMessage()}");
            }
        }
        
        $this->info("🎉 Fixed {$fixed} role assignments!");
        return 0;
    }
}
