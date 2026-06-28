<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with([
            'category:id,name,slug',
            'allVariants'
        ]);
        if ($request->filled('keyword')) {
            $kw = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', $kw)
                    ->orWhere('slug', 'like', $kw);
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
        return response()->json(
            $query->latest()->paginate($perPage)
        );
    }
    public function show(Product $product)
    {
        return response()->json(
            $product->load(['category', 'allVariants'])
        );
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price_display' => ['required', 'string', 'max:50'],
            'price_original' => ['nullable', 'string', 'max:50'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cpu' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
            'image_main' => ['required', 'string', 'max:2048'],
            'image_hover' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.group_key' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.group_label' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.option_label' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price_display' => ['nullable', 'string', 'max:50'],
            'variants.*.price_original' => ['nullable', 'string', 'max:50'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ]);
        $product = DB::transaction(function () use ($validated) {
            $data = collect($validated)->except('variants')->toArray();
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($validated['name']);
            }
             $data['stock'] = $data['stock'] ?? 0;
             $data['is_active'] = $data['is_active'] ?? true;
              $product = Product::create($data);
            foreach ($validated['variants'] ?? [] as $i => $variant) {
                $product->allVariants()->create([
                    'group_key' => $variant['group_key'],
                    'group_label' => $variant['group_label'],
                    'option_label' => $variant['option_label'],
                    'sku' => $variant['sku'] ?? null,
                    'price_display' => $variant['price_display'] ?? null,
                    'price_original' => $variant['price_original'] ?? null,
                    'stock' => $variant['stock'] ?? 0,
                    'sort_order' => $variant['sort_order'] ?? $i,
                    'is_active' => $variant['is_active'] ?? true,
                ]);
            }
            return $product;
        });
        return response()->json(
            $product->load(['category', 'allVariants']),
            201
        );
    }
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'category_id' => ['nullable', 'exists:categories,id'],
            'price_display' => ['required', 'string', 'max:50'],
            'price_original' => ['nullable', 'string', 'max:50'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cpu' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
            'image_main' => ['required', 'string', 'max:2048'],
            'image_hover' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.group_key' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.group_label' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.option_label' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price_display' => ['nullable', 'string', 'max:50'],
            'variants.*.price_original' => ['nullable', 'string', 'max:50'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ]);
        DB::transaction(function () use ($validated, $product) {
            $data = collect($validated)->except('variants')->toArray();
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($validated['name']);
            }
            $data['stock'] = $data['stock'] ?? 0;
            $data['is_active'] = $data['is_active'] ?? true;
            $product->update($data);
            $this->syncVariants($product, $validated['variants'] ?? []);
        });
        return response()->json(
            $product->load(['category', 'allVariants'])
        );
    }
    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $product->allVariants()->delete();
            $product->delete();
        });
        return response()->json([
            'message' => 'Đã xóa sản phẩm'
        ]);
    }
    private function syncVariants(Product $product, array $variants): void
    {
        $keepIds = [];
        foreach ($variants as $i => $variant) {
            $payload = [
                'group_key' => $variant['group_key'],
                'group_label' => $variant['group_label'],
                'option_label' => $variant['option_label'],
                'sku' => $variant['sku'] ?? null,
                'price_display' => $variant['price_display'] ?? null,
                'price_original' => $variant['price_original'] ?? null,
                'stock' => $variant['stock'] ?? 0,
                'sort_order' => $variant['sort_order'] ?? $i,
                'is_active' => $variant['is_active'] ?? true,
            ];
            if (!empty($variant['id'])) {
                $existing = $product->allVariants()->withTrashed()->find($variant['id']);
                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update($payload);
                    $keepIds[] = $existing->id;
                    continue;
                }
            }
            $created = $product->allVariants()->create($payload);
            $keepIds[] = $created->id;
        }
        $product->allVariants()
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(fn ($v) => $v->delete());
    }
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,webp,jpg', 'max:5120'],
        ]);
        $path = $request->file('image')->store('products', 'public');
        return response()->json([
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}