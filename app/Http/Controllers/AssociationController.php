<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Association;

class AssociationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $associations = Association::all();
        return response()->json($associations);
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
    public function show(Association $association)
    {
        $farmers = $association->farmers()->paginate(10);

        $genderStats = [
            'male' => $association->farmers()->where('gender', 'Male')->count(),
            'female' => $association->farmers()->where('gender', 'Female')->count(),
        ];

        return response()->json(['association' => $association, 'farmers' => $farmers, 'genderStats' => $genderStats]);
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
