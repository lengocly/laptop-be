<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (API ProductController)
 * =============================================================================
 * - Controller = lớp nhận request HTTP, xử lý logic, trả response (ở đây là JSON).
 * - Tách biệt với Model: Model nói chuyện với database; Controller điều phối và định dạng dữ liệu trả về.
 *
 * HÀM index():
 * - Được gọi khi client gửi GET /api/v1/product (xem routes/api.php).
 * - Luồng xử lý:
 *   1) Product::query() — bắt đầu truy vấn bảng products qua Eloquent.
 *   2) where('is_active', true) — chỉ lấy sản phẩm đang bán.
 *   3) orderBy('id') — sắp xếp ổn định theo id.
 *   4) get() — thực thi SQL, lấy collection bản ghi.
 *   5) map(...) — đổi mỗi bản ghi DB sang đúng cấu trúc React cần:
 *      id, name, price, images, và category (id, name, slug, parent_id) để sau này lọc/menu.
 *   6) Query ?category=slug — chỉ lấy SP thuộc danh mục có slug đó (vd: laptop-gaming).
 *   7) response()->json(['contents' => ...]) — trả JSON { "contents": [ ... ] }.
 *
 * Vì sao cần đúng format `contents`?
 * - Trang HomePage React đang làm: setListProducts(res.contents).
 *
 * HÀM show($id):
 * - GET /api/v1/product/{id} — một sản phẩm đầy đủ cho trang chi tiết (ảnh, thông số, danh mục + cha để breadcrumb).
 * =============================================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('id');

        if ($slug = $request->query('category')) {
            $query->whereHas('category', function ($q) use ($slug): void {
                $q->where('slug', $slug);
            });
        }

        $contents = $query
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price_display,
                'images' => array_values(array_filter([$p->image_main, $p->image_hover])),
                'category' => $p->category ? [
                    'id' => $p->category->id,
                    'name' => $p->category->name,
                    'slug' => $p->category->slug,
                    'parent_id' => $p->category->parent_id,
                ] : null,
            ])
            ->values()
            ->all();

        return response()->json(['contents' => $contents]);
    }

    public function show(int $id): JsonResponse
    {
        $p = Product::query()
            ->with(['category.parent'])
            ->where('is_active', true)
            ->find($id);

        if (! $p) {
            throw new NotFoundHttpException('Product not found');
        }

        $images = array_values(array_unique(array_filter([$p->image_main, $p->image_hover])));

        $category = null;
        if ($p->category) {
            $category = [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug,
                'parent_id' => $p->category->parent_id,
            ];
            if ($p->category->parent) {
                $category['parent'] = [
                    'id' => $p->category->parent->id,
                    'name' => $p->category->parent->name,
                    'slug' => $p->category->parent->slug,
                ];
            }
        }

        return response()->json([
            'product' => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price_display,
                'images' => $images,
                'cpu' => $p->cpu,
                'ram' => $p->ram,
                'storage' => $p->storage,
                'screen' => $p->screen,
                'stock' => $p->stock,
                'category' => $category,
            ],
        ]);
    }
}
