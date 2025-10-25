<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index()
    {
        $categories = Category::all();

       return response()->json([
            'message' => 'Categories fetched successfully',
            'data' => $categories,
            'response' => 200,
            'success' => true,
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:categories,name',
        ]);

        $data['slug'] = \Str::slug($data['name']);

        $category = Category::create($data);
        return response()->json(['message' => 'Category created', 'category' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|unique:categories,name,' . $id,
        ]);
        $data['slug'] = \Str::slug($data['name']);
        $category->update($data);
        return response()->json(['message' => 'Category updated', 'category' => $category]);
    }

    public function destroy($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Category deleted']);
    }
}
