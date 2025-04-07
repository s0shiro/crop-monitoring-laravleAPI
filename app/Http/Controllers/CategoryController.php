<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * Get all categories.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $categories = Category::all(['id', 'name']);
        return response()->json($categories);
    }
}
