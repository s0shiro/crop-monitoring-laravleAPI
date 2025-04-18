<?php

namespace App\Http\Controllers;

use App\Models\CropPlanting;
use App\Models\HarvestReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class HarvestReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
            'crop_planting_id' => 'nullable|exists:crop_plantings,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9;

        $query = HarvestReport::with(['cropPlanting.farmer', 'technician'])
            ->when(Auth::user()->hasRole('technician'), function ($query) {
                $query->where('technician_id', Auth::id());
            })
            ->when($request->crop_planting_id, function ($query) use ($request) {
                $query->where('crop_planting_id', $request->crop_planting_id);
            })
            ->when($request->date_from, function ($query) use ($request) {
                $query->whereDate('harvest_date', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($query) use ($request) {
                $query->whereDate('harvest_date', '<=', $request->date_to);
            });

        $reports = $query->orderBy('harvest_date', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get();

        $nextCursor = $reports->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $reports->take($limit),
            'nextCursor' => $nextCursor
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'crop_planting_id' => [
                'required',
                'exists:crop_plantings,id',
                function ($attribute, $value, $fail) {
                    $planting = CropPlanting::find($value);
                    
                    if (!$planting) {
                        return; // Let the 'exists' validation handle this
                    }

                    if ($planting->remaining_area <= 0) {
                        $fail('This crop planting has no remaining area to harvest.');
                        return;
                    }

                    if (!$planting->canBeHarvested()) {
                        $fail('This crop planting is not in harvestable status.');
                        return;
                    }

                    if (Auth::user()->hasRole('technician')) {
                        if ($planting->technician_id !== Auth::id()) {
                            $fail('You can only harvest your assigned crop plantings.');
                        }
                    }
                },
            ],
            'harvest_date' => 'required|date',
            'area_harvested' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request) {
                    if ($planting = CropPlanting::find($request->crop_planting_id)) {
                        if ($value > $planting->remaining_area) {
                            $fail("The harvest area ({$value} ha) cannot exceed the remaining area ({$planting->remaining_area} ha).");
                        }
                    }
                }
            ],
            'total_yield' => 'required|numeric|min:0',
            'profit' => 'nullable|numeric|min:0',
            'damage_quantity' => 'nullable|numeric|min:0'
        ]);

        try {
            $report = DB::transaction(function () use ($request) {
                $cropPlanting = CropPlanting::findOrFail($request->crop_planting_id);
                
                // Create harvest report
                $report = HarvestReport::create([
                    'crop_planting_id' => $cropPlanting->id,
                    'technician_id' => Auth::id(),
                    'harvest_date' => $request->harvest_date,
                    'area_harvested' => $request->area_harvested,
                    'total_yield' => $request->total_yield,
                    'profit' => $request->profit ?? 0,
                    'damage_quantity' => $request->damage_quantity ?? 0
                ]);

                // Update crop planting areas and status
                $newHarvestedArea = $cropPlanting->harvested_area + $request->area_harvested;
                $newRemainingArea = $cropPlanting->remaining_area - $request->area_harvested;

                $cropPlanting->update([
                    'harvested_area' => $newHarvestedArea,
                    'remaining_area' => $newRemainingArea,
                    'status' => $newRemainingArea <= 0 ? 'harvested' : 'partially harvested'
                ]);

                return $report;
            });

            return response()->json([
                'message' => 'Harvest report created successfully',
                'data' => $report->load(['cropPlanting.farmer', 'technician'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating harvest report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(HarvestReport $harvestReport): JsonResponse
    {
        if (!$this->canAccessHarvestReport($harvestReport)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $harvestReport->load(['cropPlanting.farmer', 'technician'])
        ]);
    }

    protected function canAccessHarvestReport(HarvestReport $report): bool
    {
        if (Auth::user()->hasRole('admin')) {
            return true;
        }

        if (Auth::user()->hasRole('technician')) {
            return $report->technician_id === Auth::id();
        }

        return false;
    }
}