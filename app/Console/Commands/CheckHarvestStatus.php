<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CropPlanting;

class CheckHarvestStatus extends Command
{
    protected $signature = 'crop:check-harvest-status';
    protected $description = 'Check and update harvest status for crop plantings based on expected harvest date';

    public function handle()
    {
        $this->info('Checking harvest status for crop plantings...');

        $affected = CropPlanting::where('status', '!=', 'harvest')
            ->whereNotNull('expected_harvest_date')
            ->where('expected_harvest_date', '<=', now()->startOfDay())
            ->where('remaining_area', '>', 0)
            ->update(['status' => 'harvest']);

        $this->info("Updated {$affected} crop planting(s) to harvest status.");
    }
}