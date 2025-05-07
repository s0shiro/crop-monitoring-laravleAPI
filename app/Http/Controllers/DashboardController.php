<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Farmer;
use App\Models\CropPlanting;
use App\Models\CropInspection;
use App\Models\HarvestReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    public function getStats(): JsonResponse
    {
        if (Auth::user()->hasRole('admin')) {
            return $this->getAdminStats();
        } elseif (Auth::user()->hasRole('technician')) {
            return $this->getTechnicianStats();
        } elseif (Auth::user()->hasRole('coordinator')) {
            return $this->getCoordinatorStats();
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    private function getAdminStats(): JsonResponse
    {
        $currentYear = Carbon::now()->year;

        // Get total users count by role
        $userStats = $this->getUserStats();

        // Get active farms/fields count
        $activeFields = CropPlanting::where('status', 'standing')->count();

        // Get user activity trends
        $userActivity = $this->getUserActivity();

        // Get analytics data
        $analytics = [
            'crop_plantings_by_category' => $this->getCropPlantingsData($currentYear),
            'harvest_analytics' => $this->getHarvestAnalytics($currentYear)
        ];

        return response()->json([
            'system_overview' => [
                'total_users' => array_sum($userStats),
                'users_by_role' => $userStats,
                'active_fields' => $activeFields,
                'total_farmers' => Farmer::count(),
            ],
            'analytics' => $analytics
        ]);
    }

    private function getUserStats(): array
    {
        return User::select('roles.name as role', DB::raw('count(*) as count'))
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->groupBy('roles.name')
            ->get()
            ->pluck('count', 'role')
            ->toArray();
    }

    private function getUserActivity(): array
    {
        return CropInspection::select(
            DB::raw('DATE(inspection_date) as date'),
            DB::raw('count(*) as inspections')
        )
            ->where('inspection_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }

    private function initializeMonthlyData(array $categories): array
    {
        $data = [];
        foreach ($categories as $category) {
            $data[$category] = array_fill(1, 12, 0);
        }
        return $data;
    }

    private function getCropPlantingsData(int $year): array
    {
        $stats = CropPlanting::select(
            'categories.name as category',
            DB::raw("to_char(crop_plantings.created_at, 'MM') as month"),
            DB::raw('count(*) as count')
        )
            ->join('crops', 'crop_plantings.crop_id', '=', 'crops.id')
            ->join('categories', 'crops.category_id', '=', 'categories.id')
            ->whereYear('crop_plantings.created_at', $year)
            ->groupBy('categories.name', 'month')
            ->orderBy('categories.name')
            ->orderBy('month')
            ->get();

        $categories = $stats->pluck('category')->unique();
        $categoryData = $this->initializeMonthlyData($categories->toArray());

        foreach ($stats as $stat) {
            $categoryData[$stat->category][(int)$stat->month] = $stat->count;
        }

        return [
            'labels' => self::MONTHS,
            'datasets' => collect($categoryData)->map(fn($data, $category) => [
                'label' => $category,
                'data' => array_values($data)
            ])->values()->toArray()
        ];
    }

    private function getHarvestAnalytics(int $year): array
    {
        $stats = HarvestReport::select(
            'categories.name as category',
            DB::raw("to_char(harvest_date, 'MM') as month"),
            DB::raw('SUM(area_harvested) as total_area'),
            DB::raw('SUM(total_yield) as total_yield')
        )
            ->join('crop_plantings', 'harvest_reports.crop_planting_id', '=', 'crop_plantings.id')
            ->join('crops', 'crop_plantings.crop_id', '=', 'crops.id')
            ->join('categories', 'crops.category_id', '=', 'categories.id')
            ->whereYear('harvest_date', $year)
            ->groupBy('categories.name', 'month')
            ->orderBy('categories.name')
            ->orderBy('month')
            ->get();

        $categories = $stats->pluck('category')->unique();
        $categoryData = [];
        
        foreach ($categories as $category) {
            $categoryData[$category] = [
                'area' => array_fill(1, 12, 0),
                'yield' => array_fill(1, 12, 0)
            ];
        }

        foreach ($stats as $stat) {
            $monthIndex = (int)$stat->month;
            $categoryData[$stat->category]['area'][$monthIndex] = round(floatval($stat->total_area), 2);
            $categoryData[$stat->category]['yield'][$monthIndex] = round(floatval($stat->total_yield), 2);
        }

        return [
            'labels' => self::MONTHS,
            'area_harvested' => [
                'datasets' => collect($categoryData)->map(fn($data, $category) => [
                    'label' => $category,
                    'data' => array_values($data['area'])
                ])->values()->toArray()
            ],
            'total_yield' => [
                'datasets' => collect($categoryData)->map(fn($data, $category) => [
                    'label' => $category,
                    'data' => array_values($data['yield'])
                ])->values()->toArray()
            ]
        ];
    }

    private function getTechnicianStats(): JsonResponse
    {
        $technicianId = Auth::id();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Get total crop plantings
        $totalCropPlantings = CropPlanting::where('technician_id', $technicianId)->count();
        
        // Get active crop plantings
        $activeCropPlantings = CropPlanting::where('technician_id', $technicianId)
            ->where('status', 'standing')
            ->count();

        // Get total handled farmers
        $totalFarmers = Farmer::where('technician_id', $technicianId)->count();

        // Get scheduled field visits (inspections for the next 7 days)
        $upcomingInspections = CropInspection::where('technician_id', $technicianId)
            ->where('inspection_date', '>=', Carbon::now())
            ->where('inspection_date', '<=', Carbon::now()->addDays(7))
            ->with(['cropPlanting:id,farmer_id', 'cropPlanting.farmer:id,name'])
            ->orderBy('inspection_date')
            ->get()
            ->map(function ($inspection) {
                return [
                    'date' => $inspection->inspection_date,
                    'farmer_name' => $inspection->cropPlanting->farmer->name ?? 'N/A',
                ];
            });

        // Get recent alerts (damage reports from inspections)
        $recentAlerts = CropInspection::where('technician_id', $technicianId)
            ->where('damaged_area', '>', 0)
            ->with(['cropPlanting:id,farmer_id', 'cropPlanting.farmer:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($inspection) {
                return [
                    'date' => $inspection->inspection_date,
                    'farmer_name' => $inspection->cropPlanting->farmer->name ?? 'N/A',
                    'damaged_area' => $inspection->damaged_area,
                    'remarks' => $inspection->remarks,
                ];
            });

        return response()->json([
            'overview' => [
                'total_crop_plantings' => $totalCropPlantings,
                'active_crop_plantings' => $activeCropPlantings,
                'total_farmers' => $totalFarmers,
            ],
            'monitoring' => [
                'upcoming_inspections' => $upcomingInspections,
                'recent_alerts' => $recentAlerts,
            ]
        ]);
    }

    private function getCoordinatorStats(): JsonResponse
    {
        $coordinatorId = Auth::id();
        $currentYear = Carbon::now()->year;

        // Get total technicians count
        $totalTechnicians = User::where('coordinator_id', $coordinatorId)
            ->whereHas('roles', function($query) {
                $query->where('name', 'technician');
            })->count();

        // Get total active crop plantings under coordinator's technicians
        $activeCropPlantings = CropPlanting::whereHas('technician', function($query) use ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        })
        ->where('status', 'standing')
        ->count();

        // Get total farmers under coordinator's technicians
        $totalFarmers = Farmer::whereHas('technician', function($query) use ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        })->count();

        // Get recent inspections by coordinator's technicians
        $recentInspections = CropInspection::whereHas('technician', function($query) use ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        })
        ->with(['cropPlanting:id,farmer_id', 'cropPlanting.farmer:id,name', 'technician:id,name'])
        ->orderBy('inspection_date', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($inspection) {
            return [
                'date' => $inspection->inspection_date,
                'farmer_name' => $inspection->cropPlanting->farmer->name ?? 'N/A',
                'technician_name' => $inspection->technician->name ?? 'N/A',
                'remarks' => $inspection->remarks,
            ];
        });

        // Get alerts (damage reports) from coordinator's technicians
        $recentAlerts = CropInspection::whereHas('technician', function($query) use ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        })
        ->where('damaged_area', '>', 0)
        ->with(['cropPlanting:id,farmer_id', 'cropPlanting.farmer:id,name', 'technician:id,name'])
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($inspection) {
            return [
                'date' => $inspection->inspection_date,
                'farmer_name' => $inspection->cropPlanting->farmer->name ?? 'N/A',
                'technician_name' => $inspection->technician->name ?? 'N/A',
                'damaged_area' => $inspection->damaged_area,
                'remarks' => $inspection->remarks,
            ];
        });

        // Get crop planting trends for coordinator's technicians
        $plantingTrends = CropPlanting::whereHas('technician', function($query) use ($coordinatorId) {
            $query->where('coordinator_id', $coordinatorId);
        })
        ->select(
            DB::raw("to_char(created_at, 'MM') as month"),
            DB::raw('count(*) as count')
        )
        ->whereYear('created_at', $currentYear)
        ->groupBy('month')
        ->orderBy('month')
        ->get();

        $monthlyData = array_fill(1, 12, 0);
        foreach ($plantingTrends as $trend) {
            $monthlyData[(int)$trend->month] = $trend->count;
        }

        return response()->json([
            'overview' => [
                'total_technicians' => $totalTechnicians,
                'active_crop_plantings' => $activeCropPlantings,
                'total_farmers' => $totalFarmers,
            ],
            'monitoring' => [
                'recent_inspections' => $recentInspections,
                'recent_alerts' => $recentAlerts,
            ],
            'trends' => [
                'labels' => self::MONTHS,
                'datasets' => [
                    [
                        'label' => 'Crop Plantings',
                        'data' => array_values($monthlyData)
                    ]
                ]
            ]
        ]);
    }
}