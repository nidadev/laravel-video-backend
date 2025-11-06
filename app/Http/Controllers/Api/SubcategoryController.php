<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subcategory;

class SubcategoryController extends Controller
{
    //
     public function index()
    {
        $subcategories = Subcategory::with('category:id,name,slug')->get();
         return response()->json([
        'message' => 'Subcategories fetched successfully',
        'data' => $subcategories,
        'response' => 200,
        'success' => true,
    ], 200);
    }

    // Get subcategories by category ID
    public function byCategory($categoryId)
    {
        $subcategories = Subcategory::where('category_id', $categoryId)->get();
        return response()->json($subcategories);
    }

    // Optionally: create new subcategory
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subcategories',
        ]);

        $subcategory = Subcategory::create($validated);

        return response()->json(['success' => true, 'subcategory' => $subcategory]);
    }
}
