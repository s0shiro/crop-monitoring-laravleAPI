<?php

namespace App\Http\Controllers;

use App\Models\CropPlanting;
use App\Models\Farmer;
use App\Models\Category;
use App\Models\Variety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CropPlantingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
            'status' => 'nullable|in:standing,harvest,partially harvested,harvested,all',
            'search' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9;

        $query = CropPlanting::with(['farmer', 'crop', 'variety', 'category', 'hvcDetail', 'riceDetail'])
            ->when(Auth::user()->hasRole('technician'), function ($query) {
                $query->where('technician_id', Auth::id());
            });

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('farmer', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                })->orWhereHas('crop', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->date_from) {
            $query->whereDate('planting_date', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->whereDate('planting_date', '<=', $request->date_to);
        }

        $query->orderBy('created_at', 'desc');

        $plantings = $query->skip($cursor)
            ->take($limit + 1)
            ->get();

        $nextCursor = $plantings->count() > $limit ? $cursor + $limit : null;

        // Get counts for status badges
        $counts = [
            'standing' => (clone $query)->where('status', 'standing')->count(),
            'harvest' => (clone $query)->where('status', 'harvest')->count(),
            'partially_harvested' => (clone $query)->where('status', 'partially harvested')->count(),
            'harvested' => (clone $query)->where('status', 'harvested')->count(),
        ];

        return response()->json([
            'data' => $plantings->take($limit),
            'nextCursor' => $nextCursor,
            'counts' => $counts
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Base validation rules
        $validationRules = [
            'category_id' => 'required|exists:categories,id',
            'farmer_id' => [
                'required',
                'exists:farmers,id',
                function ($attribute, $value, $fail) {
                    if (Auth::user()->hasRole('technician')) {
                        $farmer = Farmer::where('id', $value)
                            ->where('technician_id', Auth::id())
                            ->first();
                        if (!$farmer) {
                            $fail('You can only create crop plantings for your assigned farmers.');
                        }
                    }
                },
            ],
            'crop_id' => 'required|exists:crops,id',
            'variety_id' => 'required|exists:varieties,id',
            'planting_date' => 'required|date',
            'area_planted' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'expenses' => 'nullable|numeric|min:0',
            'remarks' => 'required|string',
            'status' => 'required|in:standing,harvest,harvested',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'municipality' => 'required|string|max:100',
            'barangay' => 'required|string|max:100',
        ];

        // Validate base rules first
        $request->validate($validationRules);

        // Get category name after validation
        try {
            $categoryName = Category::findOrFail($request->category_id)->name;

            // Add category-specific validation
            $additionalRules = [];
            if ($categoryName === 'High Value') {
                $additionalRules['hvc_classification'] = 'required|string';
            } elseif ($categoryName === 'Rice') {
                $additionalRules['rice_classification'] = 'required|string';
                $additionalRules['water_supply'] = 'required|string';
                $additionalRules['land_type'] = 'nullable|string';
            }

            // Validate additional rules if any
            if (!empty($additionalRules)) {
                $request->validate($additionalRules);
            }

            // Proceed with database transaction
            DB::transaction(function () use ($request, $categoryName) {
                $maturityDays = Variety::where('id', $request->variety_id)->value('maturity_days');
                $expectedHarvestDate = $maturityDays ? Carbon::parse($request->planting_date)->addDays($maturityDays) : null;

                $cropPlanting = CropPlanting::create([
                    'farmer_id' => $request->farmer_id,
                    'category_id' => $request->category_id,
                    'crop_id' => $request->crop_id,
                    'variety_id' => $request->variety_id,
                    'planting_date' => $request->planting_date,
                    'expected_harvest_date' => $expectedHarvestDate,
                    'area_planted' => $request->area_planted,
                    'harvested_area' => 0,
                    'damaged_area' => 0,
                    'remaining_area' => $request->area_planted,
                    'quantity' => $request->quantity,
                    'expenses' => $request->expenses,
                    'technician_id' => Auth::id(),
                    'remarks' => $request->remarks,
                    'status' => $request->status,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'municipality' => $request->municipality,
                    'barangay' => $request->barangay,
                ]);

                // Handle category-specific details
                if ($categoryName === 'High Value') {
                    $cropPlanting->hvcDetail()->create([
                        'classification' => $request->hvc_classification
                    ]);
                } elseif ($categoryName === 'Rice') {
                    $cropPlanting->riceDetail()->create([
                        'classification' => $request->rice_classification,
                        'water_supply' => $request->water_supply,
                        'land_type' => $request->land_type
                    ]);
                }
            });

            return response()->json(['message' => 'Crop planting record created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(CropPlanting $cropPlanting): JsonResponse
    {
        if (!$this->canAccessCropPlanting($cropPlanting)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $cropPlanting->load(['farmer', 'crop', 'variety', 'category', 'technician', 'hvcDetail', 'riceDetail'])
        ]);
    }

    public function update(Request $request, CropPlanting $cropPlanting): JsonResponse
    {
        if (!$this->canAccessCropPlanting($cropPlanting)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Base validation rules
        $validationRules = [
            'category_id' => 'required|exists:categories,id',
            'farmer_id' => [
                'required',
                'exists:farmers,id',
                function ($attribute, $value, $fail) {
                    if (Auth::user()->hasRole('technician')) {
                        $farmer = Farmer::where('id', $value)
                            ->where('technician_id', Auth::id())
                            ->first();
                        if (!$farmer) {
                            $fail('You can only update crop plantings for your assigned farmers.');
                        }
                    }
                },
            ],
            'crop_id' => 'required|exists:crops,id',
            'variety_id' => 'required|exists:varieties,id',
            'planting_date' => 'required|date',
            'area_planted' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'expenses' => 'nullable|numeric|min:0',
            'remarks' => 'required|string',
            'status' => 'required|in:standing,harvest,partially harvested,harvested',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'municipality' => 'required|string|max:100',
            'barangay' => 'required|string|max:100',
        ];

        // Validate base rules first
        $request->validate($validationRules);

        try {
            // Get category name after validation
            $categoryName = Category::findOrFail($request->category_id)->name;

            // Add category-specific validation
            $additionalRules = [];
            if ($categoryName === 'High Value') {
                $additionalRules['hvc_classification'] = 'required|string';
            } elseif ($categoryName === 'Rice') {
                $additionalRules['rice_classification'] = 'required|string';
                $additionalRules['water_supply'] = 'required|string';
                $additionalRules['land_type'] = 'nullable|string';
            }

            // Validate additional rules if any
            if (!empty($additionalRules)) {
                $request->validate($additionalRules);
            }

            // Proceed with database transaction
            DB::transaction(function () use ($request, $cropPlanting, $categoryName) {
                $maturityDays = Variety::where('id', $request->variety_id)->value('maturity_days');
                $expectedHarvestDate = $maturityDays ? Carbon::parse($request->planting_date)->addDays($maturityDays) : null;

                $cropPlanting->update([
                    'farmer_id' => $request->farmer_id,
                    'category_id' => $request->category_id,
                    'crop_id' => $request->crop_id,
                    'variety_id' => $request->variety_id,
                    'planting_date' => $request->planting_date,
                    'expected_harvest_date' => $expectedHarvestDate,
                    'area_planted' => $request->area_planted,
                    'quantity' => $request->quantity,
                    'expenses' => $request->expenses,
                    'remarks' => $request->remarks,
                    'status' => $request->status,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'municipality' => $request->municipality,
                    'barangay' => $request->barangay,
                ]);

                // Handle category-specific details
                if ($categoryName === 'High Value') {
                    $cropPlanting->hvcDetail()->updateOrCreate(
                        ['crop_planting_id' => $cropPlanting->id],
                        ['classification' => $request->hvc_classification]
                    );
                    // Delete any existing rice details if category changed
                    $cropPlanting->riceDetail()->delete();
                } elseif ($categoryName === 'Rice') {
                    $cropPlanting->riceDetail()->updateOrCreate(
                        ['crop_planting_id' => $cropPlanting->id],
                        [
                            'classification' => $request->rice_classification,
                            'water_supply' => $request->water_supply,
                            'land_type' => $request->land_type
                        ]
                    );
                    // Delete any existing hvc details if category changed
                    $cropPlanting->hvcDetail()->delete();
                }
            });

            return response()->json(['message' => 'Crop planting record updated successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(CropPlanting $cropPlanting): JsonResponse
    {
        if (!$this->canAccessCropPlanting($cropPlanting)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cropPlanting->delete();
        return response()->json(['message' => 'Crop planting record deleted successfully']);
    }

    public function inspections(Request $request, CropPlanting $cropPlanting): JsonResponse
    {
        if (!$this->canAccessCropPlanting($cropPlanting)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'cursor' => 'nullable|integer|min:0',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9;

        $query = $cropPlanting->inspections()
            ->select([
                'id', 
                'inspection_date', 
                'remarks', 
                'damaged_area',
                'technician_id',
                'created_at'
            ])
            ->with([
                'technician:id,name'
            ])
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

    protected function canAccessCropPlanting(CropPlanting $cropPlanting): bool
    {
        if (Auth::user()->hasRole('admin')) {
            return true;
        }

        if (Auth::user()->hasRole('technician')) {
            return $cropPlanting->technician_id === Auth::id();
        }

        return false;
    }
}