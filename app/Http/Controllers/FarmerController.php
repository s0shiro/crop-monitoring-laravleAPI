<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Services\AdminFarmerService;
use App\Services\TechnicianFarmerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class FarmerController extends Controller
{
    protected $adminService;
    protected $technicianService;

    public function __construct(AdminFarmerService $adminService, TechnicianFarmerService $technicianService)
    {
        $this->adminService = $adminService;
        $this->technicianService = $technicianService;
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
        ]);

        $cursor = $request->input('cursor', 0);
        $service = Auth::user()->hasRole('admin') ? $this->adminService : $this->technicianService;
        $result = $service->getFarmers($cursor);

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $baseRules = [
            'name' => [
                'required',
                'string',
                'max:255',
                // Prevent duplicate name in same location
                Rule::unique('farmers')->where(function ($query) use ($request) {
                    return $query->where('name', $request->name)
                                ->where('barangay', $request->barangay)
                                ->where('municipality', $request->municipality);
                })
            ],
            'gender' => 'required|in:male,female',
            'rsbsa' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('farmers')->whereNotNull('rsbsa'), // Only check uniqueness if RSBSA is provided
            ],
            'landsize' => 'nullable|numeric|min:0',
            'barangay' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'association_id' => 'nullable|exists:associations,id',
        ];

        // Add technician_id validation only for admin
        if (Auth::user()->hasRole('admin')) {
            $baseRules['technician_id'] = 'required|exists:users,id';
        }

        $request->validate($baseRules);

        try {
            $service = Auth::user()->hasRole('admin') ? $this->adminService : $this->technicianService;
            $farmer = $service->createFarmer($request->all());

            return response()->json([
                'message' => 'Farmer created successfully',
                'data' => $farmer->load(['association', 'technician'])
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'message' => 'Invalid farmer ID format. ID must be a number.'
                ], 400);
            }

            $farmer = Farmer::findOrFail($id);

            if (!Auth::user()->hasRole('admin') && !$this->technicianService->canManageFarmer($farmer)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json([
                'data' => $farmer->load([
                    'association', 
                    'technician',
                    'cropPlantings' => function($query) {
                        $query->select(
                            'id', 
                            'farmer_id',
                            'planting_date',
                            'expected_harvest_date',
                            'area_planted',
                            'harvested_area',
                            'remaining_area',
                            'damaged_area',
                            'status',
                            'category_id',
                            'crop_id',
                            'variety_id'
                        )
                        ->with([
                            'category:id,name',
                            'crop:id,name,category_id',
                            'variety:id,name,maturity_days,crop_id',
                            'harvestReports:id,crop_planting_id,harvest_date,area_harvested,total_yield',
                            'inspections:id,crop_planting_id,inspection_date,damaged_area'
                        ])
                        ->orderBy('planting_date', 'desc');
                    }
                ])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Farmer not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid farmer ID provided'], 400);
        }
    }

    public function update(Request $request, Farmer $farmer): JsonResponse
    {
        if (!Auth::user()->hasRole('admin') && !$this->technicianService->canManageFarmer($farmer)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $baseRules = [
            'name' => [
                'required',
                'string',
                'max:255',
                // Prevent duplicate name in same location, excluding current farmer
                Rule::unique('farmers')->where(function ($query) use ($request) {
                    return $query->where('name', $request->name)
                                ->where('barangay', $request->barangay)
                                ->where('municipality', $request->municipality);
                })->ignore($farmer->id)
            ],
            'gender' => 'required|in:male,female',
            'rsbsa' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('farmers')->whereNotNull('rsbsa')->ignore($farmer->id),
            ],
            'landsize' => 'nullable|numeric|min:0',
            'barangay' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'association_id' => 'nullable|exists:associations,id',
        ];

        // Add technician_id validation only for admin
        if (Auth::user()->hasRole('admin')) {
            $baseRules['technician_id'] = 'required|exists:users,id';
        }

        $request->validate($baseRules);

        try {
            $service = Auth::user()->hasRole('admin') ? $this->adminService : $this->technicianService;
            $updatedFarmer = $service->updateFarmer($farmer, $request->all());

            return response()->json([
                'message' => 'Farmer updated successfully',
                'data' => $updatedFarmer
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function destroy(Farmer $farmer): JsonResponse
    {
        if (!Auth::user()->hasRole('admin') && !$this->technicianService->canManageFarmer($farmer)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $service = Auth::user()->hasRole('admin') ? $this->adminService : $this->technicianService;
            $service->deleteFarmer($farmer);

            return response()->json(['message' => 'Farmer deleted successfully']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}