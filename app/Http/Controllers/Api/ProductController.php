<?php

//Trả về JSON dạng: danh sách có id, name, price, images. Đó là hàm index() (index = danh sách).

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    //Nhận request + query cơ bản
    public function index(Request $request)
    {
        //Query builder — chưa chạy SQL, chỉ xây điều kiện
        //Chỉ SP đang bán
        $query = Product::where('is_active', true);

        //Có tham số category và không rỗng → mới lọc
        if ($request->filled('category')) {
            //Lấy giá trị, vd: 'chuot'
            $slug = $request->query('category');
            $query->whereHas('category', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        $products = $query
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->get();

        $contents = $products->map(fn ($p) => $this->formatListItem($p));
        return response()->json(['contents' => $contents]);
    }

    private function formatListItem(Product $p): array
    {
        $reviewCount = (int) $p->reviews_count;

        return [
            'id'              => $p->id,
            'name'            => $p->name,
            'price'           => $p->price_display,
            'price_original'  => $p->price_original,
            'stock'           => $p->stock,
            'images'          => array_values(array_filter([
                asset('storage/' . $p->image_main),
                $p->image_hover ? asset('storage/' . $p->image_hover) : null,
            ])),
            'review_count'    => $reviewCount,
            'rating_average'  => $reviewCount > 0
                ? round((float) $p->reviews_avg_rating, 1)
                : null,
        ];
    }

    public function show(string $id)
    {
        //category.parent = từ danh mục con, load thêm cha (belongsTo trên Category)
        $product = Product::with(['category.parent', 'variants'])
            ->where('is_active', true)
            ->findOrFail($id); //Nếu không tìm thấy, throw 404


        // Sản phẩm liên quan: cùng danh mục, khác id, tối đa 5
        $related = collect();
        //ban đầu chưa có sản phẩm liên quan nào, nên tạo một danh sách trống trước.

        //Kiểm tra sản phẩm có danh mục không, lấy tối đa 5 sản phẩm cùng danh mục
        if ($product->category_id) {
            $related = Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->limit(5)
                ->get();
        }

        $relatedProducts = $related
            ->map(fn ($p) => $this->formatListItem($p))
            ->values();


        //Chuyển dữ liệu biến thể sang dạng frontend cần
        //map() nghĩa là: duyệt qua từng biến thể và biến đổi dữ liệu
        $variants = $product->variants->map(function ($v) use ($product) {
            return [
                'id' => $v->id,
                'option_label' => $v->option_label,
                'stock' => $v->stock,
                'price' => $v->price_display ?? $product->price_display,
                'price_original' => $v->price_original ?? $product->price_original,
                'images' => array_values(array_filter([
                    $v->image_main
                        ? asset('storage/' . $v->image_main)
                        : null,
                    !$v->image_main && $product->image_hover
                        ? asset('storage/' . $product->image_hover)
                        : null,
                ])),
            ];
        })->values();
        //Sau khi map() xong, values() dùng để reset lại index của collection.
        
        //Tạo nhóm biến thể: màu sắc, bộ nhớ, cấu hình, ...
        $variantGroup = null;
        if ($variants->isNotEmpty()) {
            $first = $product->variants->first();
            $variantGroup = [
                'key' => $first->group_key,
                'label' => $first->group_label,
            ];
        }

            //Trả JSON — format cho React
        return response()->json([
            'product' => [
                'id'      => $product->id,
                'name'    => $product->name,
                'price'   => $product->price_display,
                'price_original' => $product->price_original,
                'stock'   => $product->stock,
                'cpu'     => $product->cpu,
                'ram'     => $product->ram,
                'storage' => $product->storage,
                'screen'  => $product->screen,
                'images'  => array_values(array_filter([
                    asset('storage/' . $product->image_main),
                    $product->image_hover ? asset('storage/' . $product->image_hover) : null,
                ])),
                'category' => $product->category ? [
                    'id'   => $product->category->id,
                    'name' => $product->category->name,
                    'parent' => $product->category->parent ? [
                        'id'   => $product->category->parent->id,
                        'name' => $product->category->parent->name,
                    ] : null,
                ] : null,
                'variant_group' => $variantGroup,
                'variants' => $variants,
            ],
            //đưa danh sách sản phẩm liên quan vào dữ liệu trả về cho frontend.
            'related_products' => $relatedProducts,
        ]);
    }
}
