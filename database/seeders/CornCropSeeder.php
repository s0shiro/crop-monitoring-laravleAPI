<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Crop;
use App\Models\Variety;

class CornCropSeeder extends Seeder
{
    public function run(): void
    {
        // Get the Corn category
        $cornCategory = Category::where('name', 'Corn')->first();
        
        if ($cornCategory) {
            // Create White Corn
            $whiteCorn = Crop::firstOrCreate(
                ['name' => 'White', 'category_id' => $cornCategory->id]
            );

            // Create varieties for White Corn with maturity days
            $whiteVarieties = [
                ['name' => 'Traditional', 'maturity_days' => 110],
                ['name' => 'Green Corn/Sweet Corn', 'maturity_days' => 75]
            ];
            
            foreach ($whiteVarieties as $variety) {
                Variety::firstOrCreate([
                    'name' => $variety['name'],
                    'crop_id' => $whiteCorn->id,
                    'maturity_days' => $variety['maturity_days']
                ]);
            }

            // Create Yellow Corn
            $yellowCorn = Crop::firstOrCreate(
                ['name' => 'Yellow', 'category_id' => $cornCategory->id]
            );

            // Create variety for Yellow Corn with maturity days
            Variety::firstOrCreate([
                'name' => 'Hybrid',
                'crop_id' => $yellowCorn->id,
                'maturity_days' => 95
            ]);
        }
    }
}