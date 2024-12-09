<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //index category
    public function index()
    {
        $categories = Category::all();
        if ($categories->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    //store category
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $category = new Category();
            $category->name = $request->name;
            $category->slug = generateUniqueSlug(Category::class, $request->name);
            $category->save();

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'category' => $category,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //show category
    public function show($slug)
    {
        $category = Category::where('slug', $slug)->first();
        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'category' => $category,
        ]);
    }

    //update category
    public function update(Request $request, $slug)
    {
        try {
            // return $request->all();
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $category = Category::where('slug', $slug)->first();
            if (!$category) {
                return response()->json([
                    'message' => 'Category not found',
                ], 404);
            }

            $category->name = $request->name;
            $category->slug = generateUniqueSlug(Category::class, $request->name);
            $category->save();

            return response()->json([
                'message' => 'Category updated successfully',
                'category' => $category,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //destroy category
    public function destroy($slug)
    {
        $category = Category::where('slug', $slug)->first();
        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }


    /*=====================
        Sub Category
    =====================*/

    //index sub category
    public function indexSubCategory()
    {
        $subCategories = SubCategory::all();
        if (!$subCategories) {
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'subCategories' => $subCategories,
        ]);
    }

    //store sub category
    public function storeSubCategory(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'required|integer',
                'name' => 'required|string|max:255',
            ]);

            $subCategory = new SubCategory();
            $subCategory->category_id = $request->category_id;
            $subCategory->name = $request->name;
            $subCategory->slug = generateUniqueSlug(SubCategory::class, $request->name);
            $subCategory->save();

            return response()->json([
                'success' => true,
                'message' => 'Sub Category created successfully',
                'subCategory' => $subCategory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //update sub category
    public function subCategoryUpdate(Request $request, $slug)
    {
        try {
            $request->validate([
                'category_id' => 'required|integer',
                'name' => 'required|string|max:255',
            ]);

            $subCategory = SubCategory::where('slug', $slug)->first();
            if (!$subCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub Category not found',
                ], 404);
            }

            $subCategory->category_id = $request->category_id;
            $subCategory->name = $request->name;
            $subCategory->slug = generateUniqueSlug(SubCategory::class, $request->name);
            $subCategory->save();

            return response()->json([
                'success' => true,
                'message' => 'Sub Category updated successfully',
                'subCategory' => $subCategory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    //destroy sub category
    public function destroySubCategory($slug)
    {
        $subCategory = SubCategory::where('slug', $slug)->first();
        if (!$subCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found',
            ], 404);
        }

        $subCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sub Category deleted successfully',
        ]);
    }

    //show sub category by category id
    public function showSubCategoryByCategoryId($category_id)
    {
        $subCategories = SubCategory::where('category_id', $category_id)->get();
        if ($subCategories->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'subCategories' => $subCategories,
        ]);
    }
}
