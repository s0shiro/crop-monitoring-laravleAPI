<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CropPlanting;
use App\Notifications\HarvestStatusNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckHarvestStatus extends Command
{
    protected $signature = 'crop:check-harvest-status';
    protected $description = 'Check and update harvest status for crop plantings based on expected harvest date';

    public function handle()
    {
        try {
            // Use database transaction for better performance and data consistency
            $updatedPlantings = DB::transaction(function () {
                $plantingsToUpdate = CropPlanting::query()
                    ->where('status', '!=', 'harvest')
                    ->whereNotNull('expected_harvest_date')
                    ->where('expected_harvest_date', '<=', now()->startOfDay())
                    ->where('remaining_area', '>', 0)
                    ->get();

                foreach ($plantingsToUpdate as $planting) {
                    $planting->update([
                        'status' => 'harvest',
                        'updated_at' => now()
                    ]);

                    // Send notification to the technician
                    if ($planting->technician) {
                        $planting->technician->notify(new HarvestStatusNotification($planting));
                    }
                }

                return $plantingsToUpdate;
            });

            $affected = $updatedPlantings->count();

            // Only log if there were actual changes
            if ($affected > 0) {
                Log::info("Crop harvest status check completed", [
                    'date' => now()->toDateTimeString(),
                    'affected_count' => $affected,
                    'environment' => app()->environment()
                ]);
                $this->info("Updated {$affected} crop planting(s) to harvest status and sent notifications.");
            } else {
                // Debug level for no changes to avoid filling logs
                Log::debug("No crop plantings needed harvest status update", [
                    'date' => now()->toDateTimeString(),
                    'environment' => app()->environment()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in harvest status check: ' . $e->getMessage(), [
                'exception' => $e,
                'date' => now()->toDateTimeString(),
                'environment' => app()->environment()
            ]);
            $this->error('Failed to check harvest status: ' . $e->getMessage());
            
            if (app()->environment('production')) {
                // Could integrate with your notification system here
                // Notify admin of production errors
            }
        }
    }
}