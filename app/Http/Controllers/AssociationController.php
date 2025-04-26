<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Association;

class AssociationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 9; // Number of associations per page

        $associations = Association::withCount('farmers')
            ->orderBy('id', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get();

        $nextCursor = $associations->count() > $limit ? $cursor + $limit : null;

        return response()->json([
            'data' => $associations->take($limit),
            'nextCursor' => $nextCursor,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:associations,name',
            'description' => 'nullable|string',
        ]);

        $association = Association::create($request->all());

        return response()->json(['message' => 'Association created successfully.', 'association' => $association], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Association $association)
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
        ]);

        $cursor = $request->input('cursor', 0);
        $limit = 10;

        $farmers = $association->farmers()
            ->orderBy('id', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get();

        $nextCursor = $farmers->count() > $limit ? $cursor + $limit : null;

        $genderStats = [
            'male' => $association->farmers()->where('gender', 'male')->count(),
            'female' => $association->farmers()->where('gender', 'female')->count(),
        ];

        return response()->json([
            'association' => $association,
            'farmers' => $farmers->take($limit),
            'genderStats' => $genderStats,
            'totalFarmers' => $association->farmers()->count(),
            'nextCursor' => $nextCursor,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Association $association)
    {
        $request->validate([
            'name' => 'required|string|unique:associations,name,' . $association->id,
            'description' => 'nullable|string',
        ]);

        $association->update($request->all());

        return response()->json(['message' => 'Association updated successfully.', 'association' => $association]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Association $association)
    {
        $association->delete();
        return response()->json(['message' => 'Association deleted successfully.']);
    }
}
