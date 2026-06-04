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
    // Danh sách sản phẩm cho admin
    public function index(Request $request)
    {
        $query = Product::with([
            'category:id,name,slug',
            'allVariants'
        ]);

        //tìm kiếm sản phẩm
        if ($request->filled('keyword')) {
            $kw = '%' . $request->keyword . '%';

            //tìm kiếm theo tên hoặc slug
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', $kw)
                    ->orWhere('slug', 'like', $kw);
            });
        }

        //lọc sản phẩm theo danh mục
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        //lọc sản phẩm theo trạng thái
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        //lấy danh sách sản phẩm
        return response()->json($query->latest()->get());
    }

    // Chi tiết sản phẩm để sửa
    public function show(Product $product)
    {
        return response()->json(
            $product->load(['category', 'allVariants'])
        );
    }

    // Thêm sản phẩm
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

        //thêm sản phẩm vào database và biến thể sản phẩm cùng lúc
        $product = DB::transaction(function () use ($validated) {
            $data = collect($validated)->except('variants')->toArray();

            //nếu slug không có thì tạo slug từ tên sản phẩm
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($validated['name']);
            }
            
             //nếu stock không có thì đặt giá trị mặc định là 0
             $data['stock'] = $data['stock'] ?? 0;
             $data['is_active'] = $data['is_active'] ?? true;

              //tạo sản phẩm vào database
              $product = Product::create($data);

            //tạo biến thể sản phẩm vào database
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

        //trả về sản phẩm và biến thể sản phẩm
        return response()->json(
            $product->load(['category', 'allVariants']),
            201
        );
    }

    // Cập nhật sản phẩm
    public function update(Request $request, Product $product)
    {
        //kiểm tra dữ liệu đầu vào
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

        //cập nhật sản phẩm và biến thể sản phẩm cùng lúc
        DB::transaction(function () use ($validated, $product) {
            $data = collect($validated)->except('variants')->toArray();

            //nếu slug không có thì tạo slug từ tên sản phẩm
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($validated['name']);
            }

            //nếu stock không có thì đặt giá trị mặc định là 0
            $data['stock'] = $data['stock'] ?? 0;
            $data['is_active'] = $data['is_active'] ?? true;

            //cập nhật sản phẩm vào database
            $product->update($data);

            //xóa biến thể cũ rồi tạo lại
            $product->allVariants()->delete();

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
        });

        return response()->json(
            $product->load(['category', 'allVariants'])
        );
    }

    // Xóa sản phẩm
    public function destroy(Product $product)
    {
        //xóa biến thể sản phẩm và sản phẩm
        DB::transaction(function () use ($product) {
            $product->allVariants()->delete();
            $product->delete();
        });

        //trả về thông báo xóa sản phẩm thành công
        return response()->json([
            'message' => 'Đã xóa sản phẩm'
        ]);
    }

    // Upload ảnh sản phẩm
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // tối đa ~5MB
        ]);

        // Lưu vào storage/app/public/products/
        $path = $request->file('image')->store('products', 'public');

        return response()->json([
            'path' => $path, // vd: products/xyz.jpg
            'url' => asset('storage/' . $path),
        ]);
    }
}