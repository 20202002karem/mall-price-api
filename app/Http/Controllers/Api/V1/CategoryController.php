<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category)
    {
        abort_if(!$category->is_active, 404);

        return new CategoryResource($category);
    }
}
