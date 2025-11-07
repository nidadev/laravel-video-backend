<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subcategory;
use App\Models\Category;


class SubcategoryController extends Controller
{
    //
    // List all subcategories
    public function index()
    {
        $subcategories = Subcategory::with('category')->get();
        return view('admin.subcategories.index', compact('subcategories'));
    }

    // Show create form
    public function create()
    {
        $categories = Category::all();
        return view('admin.subcategories.create', compact('categories'));
    }

    // Store new subcategory
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subcategories,slug',
            'category_id' => 'required|exists:categories,id',
        ]);

        Subcategory::create($request->only(['name', 'slug', 'category_id']));

        return redirect()->route('admin.subcategories.index')->with('success', 'Subcategory created successfully.');
    }

    // Show edit form
    public function edit(Subcategory $subcategory)
    {
        $categories = Category::all();
        return view('admin.subcategories.edit', compact('subcategory', 'categories'));
    }

    // Update subcategory
    public function update(Request $request, Subcategory $subcategory)
    {
        $request->validate([
            'name' => 'required|string|max:255,' . $subcategory->id,
            'slug' => 'required|string|max:255|unique:subcategories,slug,' . $subcategory->id,
            'category_id' => 'required|exists:categories,id',
        ]);

        $subcategory->update($request->only(['name', 'slug', 'category_id']));

        return redirect()->route('admin.subcategories.index')->with('success', 'Subcategory updated successfully.');
    }

    // Delete subcategory
    public function destroy(Subcategory $subcategory)
    {
        $subcategory->delete();
        return redirect()->route('admin.subcategories.index')->with('success', 'Subcategory deleted successfully.');
    }
}
