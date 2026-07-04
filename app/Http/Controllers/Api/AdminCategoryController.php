<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    // Danh sách dạng cây: nhóm cha + con (admin quản lý)
    public function index()
    {
        $parents = Category::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flat = Category::with('parent:id,name,slug')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'tree' => $parents,
            'flat' => $flat,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Đã thêm danh mục.',
            'category' => $category->load('parent'),
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $this->validateCategory($request, $category->id);

        // Không cho danh mục làm cha của chính nó
        if (!empty($validated['parent_id']) && (int) $validated['parent_id'] === $category->id) {
            return response()->json(['message' => 'Danh mục không thể là cha của chính nó.'], 422);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Đã cập nhật danh mục.',
            'category' => $category->fresh()->load('parent'),
        ]);
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Không xóa được: danh mục còn danh mục con. Hãy xóa con trước.',
            ], 422);
        }

        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Không xóa được: còn sản phẩm trong danh mục này.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Đã xóa danh mục.']);
    }

    // Upload ảnh icon danh mục từ máy tính
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,webp,jpg', 'max:5120'],
        ]);

        $path = $request->file('image')->store('categories', 'public');

        return response()->json([
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }

    private function validateCategory(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($ignoreId),
            ],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'image' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_featured'] = $request->boolean('is_featured');

        return $validated;
    }
}
