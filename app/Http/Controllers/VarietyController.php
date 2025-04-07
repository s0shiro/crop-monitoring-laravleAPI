<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Variety;
use Illuminate\Http\JsonResponse;
use App\Models\Crop;
use Illuminate\Pagination\LengthAwarePaginator;

class VarietyController extends Controller
{
    /**
     * Get all varieties for a specific crop with pagination.
     */
    public function index($cropId, Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9; // Number of varieties per page

        $varieties = Variety::where('crop_id', $cropId)
            ->skip($cursor)
            ->take($limit + 1) // Fetch one extra to check for next page
            ->get();

        $nextCursor = $varieties->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $varieties->take($limit), // Return only the requested number of varieties
            'nextCursor' => $nextCursor,
        ]);
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
