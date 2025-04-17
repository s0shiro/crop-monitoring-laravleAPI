<?php

namespace App\Http\Controllers;

use App\Models\CropInspection;
use App\Models\CropPlanting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CropInspectionController extends Controller
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

        $query = CropInspection::with(['cropPlanting.farmer', 'technician'])
            ->when(Auth::user()->hasRole('technician'), function ($query) {
                $query->where('technician_id', Auth::id());
            })
            ->when($request->crop_planting_id, function ($query) use ($request) {
                $query->where('crop_planting_id', $request->crop_planting_id);
            })
            ->when($request->date_from, function ($query) use ($request) {
                $query->whereDate('inspection_date', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($query) use ($request) {
                $query->whereDate('inspection_date', '<=', $request->date_to);
            });

        $inspections = $query->orderBy('inspection_date', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get();

        $nextCursor = $inspections->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $inspections->take($limit),
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

                    if ($planting->status !== 'standing') {
                        $fail('Can only inspect crop plantings with standing status.');
                        return;
                    }

                    if (Auth::user()->hasRole('technician')) {
                        if ($planting->technician_id !== Auth::id()) {
                            $fail('You can only inspect your assigned crop plantings.');
                        }
                    }
                },
            ],
            'inspection_date' => 'required|date',
            'remarks' => 'required|string',
            'damaged_area' => 'required|numeric|min:0',
        ]);

        try {
            $inspection = DB::transaction(function () use ($request) {
                // Create inspection record
                $inspection = CropInspection::create([
                    'crop_planting_id' => $request->crop_planting_id,
                    'technician_id' => Auth::id(),
                    'inspection_date' => $request->inspection_date,
                    'remarks' => $request->remarks,
                    'damaged_area' => $request->damaged_area,
                ]);

                // Update crop planting remaining and damaged areas
                $cropPlanting = CropPlanting::findOrFail($request->crop_planting_id);
                $newRemainingArea = $cropPlanting->remaining_area - $request->damaged_area;
                $newDamagedArea = $cropPlanting->damaged_area + $request->damaged_area;
                
                if ($newRemainingArea < 0) {
                    throw new \Exception('Damaged area cannot exceed remaining area.');
                }

                $cropPlanting->update([
                    'remaining_area' => $newRemainingArea,
                    'damaged_area' => $newDamagedArea
                ]);

                return $inspection;
            });

            return response()->json([
                'message' => 'Inspection record created successfully',
                'data' => $inspection->load(['cropPlanting.farmer', 'technician'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating inspection record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(CropInspection $cropInspection): JsonResponse
    {
        if (!$this->canAccessInspection($cropInspection)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $cropInspection->load(['cropPlanting.farmer', 'technician'])
        ]);
    }

    public function update(Request $request, CropInspection $cropInspection): JsonResponse
    {
        if (!$this->canAccessInspection($cropInspection)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'inspection_date' => 'required|date',
            'remarks' => 'required|string',
            'damaged_area' => 'required|numeric|min:0',
        ]);

        try {
            $cropInspection->update([
                'inspection_date' => $request->inspection_date,
                'remarks' => $request->remarks,
                'damaged_area' => $request->damaged_area,
            ]);

            return response()->json([
                'message' => 'Inspection record updated successfully',
                'data' => $cropInspection->load(['cropPlanting.farmer', 'technician'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating inspection record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(CropInspection $cropInspection): JsonResponse
    {
        if (!$this->canAccessInspection($cropInspection)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $cropInspection->delete();
            return response()->json(['message' => 'Inspection record deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting inspection record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function canAccessInspection(CropInspection $inspection): bool
    {
        if (Auth::user()->hasRole('admin')) {
            return true;
        }

        if (Auth::user()->hasRole('technician')) {
            return $inspection->technician_id === Auth::id();
        }

        return false;
    }
}