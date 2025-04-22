<?php

namespace App\Http\Controllers;

use App\Models\CropPlanting;
use App\Models\Category;
use App\Models\RiceDetail;
use App\Models\HarvestReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ReportGenerationController extends Controller
{
    /**
     * Get rice standing report data
     */
    public function getRiceStandingReport(): JsonResponse
    {
        // Get all standing rice crop plantings
        $riceCategoryId = Category::where('name', 'Rice')->value('id');
        
        $query = CropPlanting::with(['riceDetail', 'farmer'])
            ->where('category_id', $riceCategoryId)
            ->where('status', 'standing');
            
        // Apply role-based filters
        if (Auth::user()->hasRole('technician')) {
            $query->where('technician_id', Auth::id());
        }
        
        $plantings = $query->get();
        
        // Process data for the report
        $municipalities = ['Boac', 'Buenavista', 'Gasan', 'Mogpog', 'Santa Cruz', 'Torrijos'];
        $categories = ['irrigated', 'rainfed', 'upland'];
        $stages = [
            'Newly Planted',
            'Vegetative Stage',
            'Reproductive Stage',
            'Maturing Stage'
        ];
        
        // Initialize the data structure
        $processedData = [
            'Marinduque' => []
        ];
        
        foreach ($categories as $category) {
            $processedData['Marinduque'][$category] = [];
            foreach ($stages as $stage) {
                $processedData['Marinduque'][$category][$stage] = 0;
            }
            $processedData['Marinduque'][$category]['total'] = 0;
        }
        
        // Initialize municipality data with the same structure
        foreach ($municipalities as $municipality) {
            $processedData[$municipality] = [];
            foreach ($categories as $category) {
                $processedData[$municipality][$category] = [];
                foreach ($stages as $stage) {
                    $processedData[$municipality][$category][$stage] = 0;
                }
                $processedData[$municipality][$category]['total'] = 0;
            }
        }
        
        // Stage mapping based on remarks field
        $stageMapping = [
            'newly planted' => 'Newly Planted',
            'seedling' => 'Newly Planted',
            'vegetative' => 'Vegetative Stage',
            'reproductive' => 'Reproductive Stage',
            'maturing' => 'Maturing Stage',
            'mature' => 'Maturing Stage',
        ];
        
        // Process each planting record
        foreach ($plantings as $planting) {
            $municipality = $planting->municipality;
            
            // Determine category based on water_supply and land_type
            if ($planting->riceDetail && $planting->riceDetail->water_supply === 'irrigated') {
                $category = 'irrigated';
            } elseif ($planting->riceDetail && $planting->riceDetail->land_type === 'upland') {
                $category = 'upland';
            } else {
                $category = 'rainfed';
            }
            
            // Determine stage from remarks
            $remarks = strtolower($planting->remarks);
            $stage = 'Newly Planted'; // Default stage
            
            foreach ($stageMapping as $key => $value) {
                if (strpos($remarks, $key) !== false) {
                    $stage = $value;
                    break;
                }
            }
            
            // Add data to the appropriate categories
            $processedData[$municipality][$category][$stage] += $planting->area_planted;
            $processedData[$municipality][$category]['total'] += $planting->area_planted;
            
            // Add to Marinduque totals
            $processedData['Marinduque'][$category][$stage] += $planting->area_planted;
            $processedData['Marinduque'][$category]['total'] += $planting->area_planted;
        }
        
        return response()->json([
            'data' => $processedData,
            'municipalities' => array_merge(['Marinduque'], $municipalities),
            'categories' => $categories,
            'stages' => array_merge($stages, ['TOTAL']),
            'meta' => [
                'generated_at' => Carbon::now()->format('F d, Y'),
                'user' => Auth::user()->name
            ]
        ]);
    }

    /**
     * Get rice harvesting report data
     */
    public function getRiceHarvestReport(Request $request): JsonResponse
    {
        $request->validate([
            'municipality' => 'required|string',
            'water_supply' => 'required|in:irrigated,rainfed,upland,total',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Get the data from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $selectedMunicipality = $request->input('municipality');
        $selectedWaterSupply = $request->input('water_supply');
        
        // Get rice category ID
        $riceCategoryId = Category::where('name', 'Rice')->value('id');
        
        // Initialize query to get harvest reports
        $query = HarvestReport::with([
                'cropPlanting.farmer',
                'cropPlanting.riceDetail',
                'cropPlanting.variety',
            ])
            ->whereHas('cropPlanting', function($q) use ($riceCategoryId) {
                $q->where('category_id', $riceCategoryId);
            })
            ->whereBetween('harvest_date', [$startDate, $endDate]);
            
        // Apply role-based filters
        if (Auth::user()->hasRole('technician')) {
            $query->where('technician_id', Auth::id());
        }
        
        // Get the data
        $harvestReports = $query->get();
        
        // Process the data for the report
        $processedData = $this->processHarvestData(
            $harvestReports, 
            $selectedMunicipality,
            $selectedWaterSupply
        );
        
        // Calculate season and year
        $startDateObj = Carbon::parse($startDate);
        $seasonAndYear = $this->getSeasonAndYear($startDateObj);
        
        return response()->json([
            'data' => $processedData,
            'meta' => [
                'municipality' => $selectedMunicipality,
                'water_supply' => $selectedWaterSupply,
                'season_year' => $seasonAndYear,
                'date_range' => $this->formatDateRange($startDate, $endDate),
                'generated_by' => Auth::user()->name,
                'generated_at' => Carbon::now()->format('F d, Y')
            ]
        ]);
    }
    
    private function processHarvestData($harvestReports, $selectedMunicipality, $selectedWaterSupply): array
    {
        $processedData = [];
        
        foreach ($harvestReports as $report) {
            $cropPlanting = $report->cropPlanting;
            
            // Skip if not matching the selected municipality
            if ($cropPlanting->municipality !== $selectedMunicipality) {
                continue;
            }
            
            $barangay = $cropPlanting->barangay;
            $farmer = $cropPlanting->farmer;
            $farmerId = $farmer->id;
            
            // Get rice details
            $riceDetail = $cropPlanting->riceDetail;
            $waterSupply = $riceDetail ? $riceDetail->water_supply : 'rainfed';
            $landType = $riceDetail ? $riceDetail->land_type : 'lowland';
            
            // Skip based on water supply filter
            if ($selectedWaterSupply !== 'total') {
                if ($selectedWaterSupply === 'upland' && $landType !== 'upland') {
                    continue;
                }
                if ($selectedWaterSupply !== 'upland' && $waterSupply !== $selectedWaterSupply) {
                    continue;
                }
            }
            
            // Get classification from rice_detail, handling lowercase values from frontend
            $classification = strtolower($riceDetail ? $riceDetail->classification : 'good quality');
            
            // Map the frontend values to the report structure keys
            $seedTypeMap = [
                'farmer saved seeds' => 'farmerSavedSeeds',
                'hybrid' => 'hybridSeeds',
                'registered' => 'registeredSeeds',
                'certified' => 'certifiedSeeds',
                'good quality' => 'goodQualitySeeds'
            ];
            
            // Get the seed type key, defaulting to goodQualitySeeds if not found
            $seedType = $seedTypeMap[$classification] ?? 'goodQualitySeeds';
            
            // Initialize barangay data if not exists
            if (!isset($processedData[$barangay])) {
                $processedData[$barangay] = [
                    'farmerIds' => [],
                    'noOfFarmerHarvested' => 0,
                    'hybridSeeds' => ['area' => 0, 'averageYield' => 0, 'production' => 0],
                    'registeredSeeds' => ['area' => 0, 'averageYield' => 0, 'production' => 0],
                    'certifiedSeeds' => ['area' => 0, 'averageYield' => 0, 'production' => 0],
                    'goodQualitySeeds' => ['area' => 0, 'averageYield' => 0, 'production' => 0],
                    'farmerSavedSeeds' => ['area' => 0, 'averageYield' => 0, 'production' => 0],
                ];
            }
            
            // Add farmer ID if not already counted
            if (!in_array($farmerId, $processedData[$barangay]['farmerIds'])) {
                $processedData[$barangay]['farmerIds'][] = $farmerId;
                $processedData[$barangay]['noOfFarmerHarvested'] += 1;
            }
            
            // Calculate area and production from the harvest report
            $area = floatval($report->area_harvested);
            $yieldQuantity = floatval($report->total_yield ?? 0);
            
            // Convert kg to metric tons
            $production = $yieldQuantity / 1000;
            
            // Update seed type data
            $processedData[$barangay][$seedType]['area'] += $area;
            $processedData[$barangay][$seedType]['production'] += $production;
        }
        
        // Calculate average yield for each seed type
        foreach ($processedData as $barangay => &$barangayData) {
            foreach (['hybridSeeds', 'registeredSeeds', 'certifiedSeeds', 'goodQualitySeeds', 'farmerSavedSeeds'] as $seedType) {
                $area = $barangayData[$seedType]['area'];
                $production = $barangayData[$seedType]['production'];
                
                // Only calculate average yield if area is greater than zero
                if ($area > 0) {
                    $barangayData[$seedType]['averageYield'] = $production / $area;
                } else {
                    $barangayData[$seedType]['averageYield'] = 0;
                }
            }
        }
        
        // Sort barangays alphabetically
        ksort($processedData);
        
        return $processedData;
    }
    
    private function getSeasonAndYear(Carbon $date): string
    {
        $month = $date->month;
        $season = ($month >= 5 && $month <= 10) ? 'WET SEASON' : 'DRY SEASON';
        return $season . ' ' . $date->year;
    }
    
    private function formatDateRange($startDate, $endDate): string
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        if ($start->month === $end->month && $start->year === $end->year) {
            return $start->format('F Y');
        } else {
            return $start->format('M d') . ' - ' . $end->format('M d, Y');
        }
    }

    /**
     * Get monthly rice planting report data
     */
    public function getMonthlyRicePlantingReport(): JsonResponse
    {
        // Get rice category ID
        $riceCategoryId = Category::where('name', 'Rice')->value('id');

        $query = CropPlanting::with([
                'variety',
                'farmer',
                'riceDetail'
            ])
            ->where('category_id', $riceCategoryId)
            ->whereMonth('planting_date', Carbon::now()->month)
            ->whereYear('planting_date', Carbon::now()->year);

        // Apply role-based filters
        if (Auth::user()->hasRole('technician')) {
            $query->where('technician_id', Auth::id());
        }

        $plantings = $query->get();

        // Group farmers by municipality to count unique farmers
        $farmersByMunicipality = [];
        foreach ($plantings as $planting) {
            // Split municipality name by spaces and properly capitalize each word
            $municipality = implode(' ', array_map('ucfirst', explode(' ', strtolower($planting->municipality))));
            if (!isset($farmersByMunicipality[$municipality])) {
                $farmersByMunicipality[$municipality] = new \Illuminate\Support\Collection();
            }
            $farmersByMunicipality[$municipality]->push($planting->farmer->id);
        }

        // Count unique farmers per municipality
        $uniqueFarmerCounts = [];
        foreach ($farmersByMunicipality as $municipality => $farmerIds) {
            $uniqueFarmerCounts[$municipality] = $farmerIds->unique()->count();
        }

        $data = $plantings->map(function ($planting) {
            // Split municipality name by spaces and properly capitalize each word
            $municipality = implode(' ', array_map('ucfirst', explode(' ', strtolower($planting->municipality))));
            
            return [
                'location_id' => [
                    'barangay' => $planting->barangay,
                    'municipality' => $municipality,
                ],
                'area_planted' => $planting->area_planted,
                'planting_date' => $planting->planting_date,
                'category_specific' => [
                    'landType' => $planting->riceDetail ? $planting->riceDetail->land_type : 'lowland',
                    'waterSupply' => $planting->riceDetail ? $planting->riceDetail->water_supply : 'rainfed',
                    'classification' => $planting->riceDetail ? $planting->riceDetail->classification : 'Good Quality'
                ],
                'crop_categoryId' => [
                    'name' => 'Rice'
                ],
                'crop_type' => [
                    'name' => $planting->crop->name ?? 'Palay'
                ],
                'variety' => [
                    'name' => $planting->variety ? $planting->variety->name : 'Unknown'
                ],
                'farmer_id' => [
                    'id' => $planting->farmer->id,
                    'name' => $planting->farmer->name
                ]
            ];
        });

        return response()->json([
            'data' => $data,
            'farmer_counts' => $uniqueFarmerCounts
        ]);
    }

    /**
     * Get monthly corn harvest report data
     */
    public function getMonthlyCornHarvestReport(Request $request): JsonResponse
    {
        // Validate request
        $request->validate([
            'municipality' => 'nullable|string'
        ]);

        $selectedMunicipality = $request->input('municipality');

        // Get corn category ID
        $cornCategoryId = Category::where('name', 'Corn')->value('id');

        // Get the current month's crop plantings that have harvest reports
        $query = CropPlanting::with([
                'crop',
                'variety',
                'farmer',
                'harvestReports' => function($query) {
                    $query->whereMonth('harvest_date', Carbon::now()->month)
                          ->whereYear('harvest_date', Carbon::now()->year);
                }
            ])
            ->where('category_id', $cornCategoryId)
            ->whereHas('harvestReports', function($query) {
                $query->whereMonth('harvest_date', Carbon::now()->month)
                      ->whereYear('harvest_date', Carbon::now()->year);
            });

        // Filter by municipality if provided
        if ($selectedMunicipality) {
            $query->where('municipality', 'LIKE', '%' . $selectedMunicipality . '%');
        }

        // Apply role-based filters
        if (Auth::user()->hasRole('technician')) {
            $query->where('technician_id', Auth::id());
        }

        $plantings = $query->get();

        // Filter out plantings with no harvest reports in the current month
        $plantings = $plantings->filter(function($planting) {
            return $planting->harvestReports->isNotEmpty();
        });

        $data = [];
        
        // Process each planting and include all its harvest reports
        foreach ($plantings as $planting) {
            // Split municipality name by spaces and properly capitalize each word
            $municipality = implode(' ', array_map('ucfirst', explode(' ', strtolower($planting->municipality))));
            
            // Process all harvest reports for this planting
            foreach ($planting->harvestReports as $harvestReport) {
                $data[] = [
                    'location_id' => [
                        'barangay' => $planting->barangay,
                        'municipality' => $municipality,
                    ],
                    'area_planted' => $planting->area_planted,
                    'crop_type' => [
                        'name' => $planting->crop->name ?? 'Unknown'
                    ],
                    'variety' => [
                        'name' => $planting->variety ? $planting->variety->name : 'Unknown'
                    ],
                    'farmer_id' => [
                        'id' => $planting->farmer->id,
                        'name' => $planting->farmer->name
                    ],
                    'harvest_records' => [
                        [
                            'area_harvested' => $harvestReport->area_harvested,
                            'yield_quantity' => $harvestReport->total_yield ?? 0,
                            'harvest_date' => $harvestReport->harvest_date
                        ]
                    ]
                ];
            }
        }

        return response()->json([
            'data' => $data
        ]);
    }
}