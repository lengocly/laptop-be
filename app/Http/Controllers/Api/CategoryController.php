<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    // API công khai — menu cửa hàng, trang chủ, lọc hãng laptop
    public function index()
    {
        $parents = Category::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = $parents->map(fn ($parent) => $this->formatParent($parent));

        return response()->json(['categories' => $categories]);
    }

    private function formatParent(Category $parent): array
    {
        return [
            'id' => $parent->id,
            'name' => $parent->name,
            'slug' => $parent->slug,
            'image' => $parent->image,
            'is_featured' => (bool) $parent->is_featured,
            'children' => $parent->children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
                'image' => $child->image,
                'is_featured' => (bool) $child->is_featured,
                'parent_slug' => $parent->slug,
            ])->values(),
        ];
    }
}
