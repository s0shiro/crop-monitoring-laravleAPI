<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Crop;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CropController extends Controller
{
    /**
     * Get all crops with their categories.
     */
    public function index(): JsonResponse
    {
        $crops = Crop::with('category')->get();
        return response()->json($crops);
    }

    /**
     * Create a new crop.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:crops,name',
            'category_id' => 'required|exists:categories,id',
        ]);

        $crop = Crop::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
        ]);

        return response()->json(['message' => 'Crop created successfully', 'crop' => $crop], 201);
    }

    /**
     * Get crops by category.
     */
    public function getByCategory(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'cursor' => 'nullable|integer|min:0',
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9; // Number of crops per page

        $crops = Crop::where('category_id', $request->category_id)
            ->withCount('varieties') // Include the count of varieties
            ->skip($cursor)
            ->take($limit + 1) // Fetch one extra to check for next page
            ->get();

        $nextCursor = $crops->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $crops->take($limit), // Return only the requested number of crops
            'nextCursor' => $nextCursor,
        ]);
    }
}
