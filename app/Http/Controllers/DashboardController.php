<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Farmer;
use App\Models\CropPlanting;
use App\Models\CropInspection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats(): JsonResponse
    {
        if (Auth::user()->hasRole('admin')) {
            return $this->getAdminStats();
        } elseif (Auth::user()->hasRole('technician')) {
            return $this->getTechnicianStats();
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    private function getAdminStats(): JsonResponse
    {
        // Get total users count by role
        $userStats = User::select('roles.name as role', DB::raw('count(*) as count'))
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->groupBy('roles.name')
            ->get()
            ->pluck('count', 'role')
            ->toArray();

        // Get active farms/fields count
        $activeFields = CropPlanting::where('status', 'standing')->count();

        // Get monthly registration statistics using PostgreSQL date formatting
        $farmRegistrations = Farmer::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();

        // Get user activity trends (based on crop inspections)
        $userActivity = CropInspection::select(
            DB::raw('DATE(inspection_date) as date'),
            DB::raw('count(*) as inspections')
        )
            ->where('inspection_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'system_overview' => [
                'total_users' => array_sum($userStats),
                'users_by_role' => $userStats,
                'active_fields' => $activeFields,
                'total_farmers' => Farmer::count(),
            ],
            'analytics' => [
                'farm_registrations' => $farmRegistrations,
                'user_activity' => $userActivity,
            ]
        ]);
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
}