<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Variety;
use Illuminate\Http\JsonResponse;
use App\Models\Crop;

class VarietyController extends Controller
{
    /**
     * Get all varieties for a specific crop.
     */
    public function index($cropId): JsonResponse
    {
        $varieties = Variety::where('crop_id', $cropId)->get();
        return response()->json($varieties);
    }

    /**
     * Add a variety to a crop.
     */
    public function store(Request $request, $cropId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'maturity_days' => 'required|integer|min:1',
        ]);

        $crop = Crop::findOrFail($cropId);

        $variety = $crop->varieties()->create([
            'name' => $request->name,
            'maturity_days' => $request->maturity_days,
        ]);

        return response()->json(['message' => 'Variety added successfully', 'variety' => $variety], 201);
    }
}
