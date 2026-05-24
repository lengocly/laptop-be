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

        // chỉ lấy SP đang bán, hiện có 2sp trong db từ dl productseeder
        // + whereHas khi có category
        $products = $query->get(); 

        //Kho lưu tên cột: price_display, image_main, image_hover
        //React cần: price, images (mảng 2 URL)

        $contents = $products->map(function ($p) {
            //map = lặp từng laptop, tạo phiên bản mới đúng format.

            return [
                'id'     => $p->id,
                'name'   => $p->name,
                'price'  => $p->price_display,
                'price_original'  => $p->price_original,

                //gộp 2 cột ảnh
                //array_filter = bỏ ảnh null
                'images' => array_values(array_filter([
                    asset('storage/' . $p->image_main),
                    $p->image_hover ? asset('storage/' . $p->image_hover) : null,
                ])),
            ];
        });
        return response()->json(['contents' => $contents]);
    }

    //chi tiết sản phẩm
    public function show(string $id)
    {
        //category.parent = từ danh mục con, load thêm cha (belongsTo trên Category)
        $product = Product::with('category.parent')
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
                ->limit(5)
                ->get();
        }

        //Chuyển dữ liệu sản phẩm sang dạng frontend cần
        $relatedProducts = $related->map(function ($p) {
            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'price'          => $p->price_display,
                'price_original' => $p->price_original,
                'images'         => array_values(array_filter([
                    asset('storage/' . $p->image_main),
                    $p->image_hover ? asset('storage/' . $p->image_hover) : null,
                ])),
            ];
        })->values();



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
            ],
            //đưa danh sách sản phẩm liên quan vào dữ liệu trả về cho frontend.
            'related_products' => $relatedProducts,
        ]);
    }
}
