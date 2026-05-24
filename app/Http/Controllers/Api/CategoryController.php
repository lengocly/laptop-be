<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        // Lấy danh mục gốc + con 
        $parents = Category::whereNull('parent_id') //nhóm cha
            ->with('children') //Load luôn con qua quan hệ children() trong model — tránh N+1 query
            ->get(); //Lấy collection (thường 2 phần tử)


            // Định dạng JSON cho React
            //map $parents thành array có cấu trúc:
        $categories = $parents->map(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->name,
                'children' => $parent->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])->values(),
            ];
        });
        return response()->json(['categories' => $categories]);
    }
}
