<?php
namespace App\Http\Controllers\Api;
use App\Models\Product;
use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('is_active', true);
        if ($request->filled('category')) {
            $slug = $request->query('category');
            $query->whereHas('category', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }
        if ($request->filled('group')) {
            $groupSlug = $request->query('group');
            $parent = Category::where('slug', $groupSlug)->whereNull('parent_id')->first();
            if ($parent) {
                $childIds = $parent->children()->pluck('id');
                $query->whereIn('category_id', $childIds);
            }
        }
        $products = $query
            ->with(['category.parent'])
            ->withAvg('reviews', 'rating')
            ->withCount(['reviews', 'variants'])
            ->get();
        $contents = $products->map(fn ($p) => $this->formatListItem($p));
        return response()->json(['contents' => $contents]);
    }
    private function formatListItem(Product $p): array
    {
        $reviewCount = (int) $p->reviews_count;
        $parent = $p->category?->parent;
        return [
            'id'              => $p->id,
            'name'            => $p->name,
            'price'           => $p->price_display,
            'price_original'  => $p->price_original,
            'stock'           => $p->stock,
            'cpu'             => $p->cpu,
            'ram'             => $p->ram,
            'storage'         => $p->storage,
            'screen'          => $p->screen,
            'parent_group_slug' => $parent?->slug,
            'images'          => array_values(array_filter([
                asset('storage/' . $p->image_main),
                $p->image_hover ? asset('storage/' . $p->image_hover) : null,
            ])),
            'review_count'    => $reviewCount,
            'rating_average'  => $reviewCount > 0
                ? round((float) $p->reviews_avg_rating, 1)
                : null,
            'has_variants'    => (int) $p->variants_count > 0,
        ];
    }
    public function show(string $id)
    {
        $product = Product::with(['category.parent', 'variants'])
            ->where('is_active', true)
            ->findOrFail($id);
        $related = collect();
        if ($product->category_id) {
            $related = Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->with(['category.parent'])
                ->withAvg('reviews', 'rating')
                ->withCount(['reviews', 'variants'])
                ->limit(5)
                ->get();
        }
        $relatedProducts = $related
            ->map(fn ($p) => $this->formatListItem($p))
            ->values();
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
        $variantGroup = null;
        if ($variants->isNotEmpty()) {
            $first = $product->variants->first();
            $variantGroup = [
                'key' => $first->group_key,
                'label' => $first->group_label,
            ];
        }
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
                        'slug' => $product->category->parent->slug,
                    ] : null,
                ] : null,
                'variant_group' => $variantGroup,
                'variants' => $variants,
            ],
            'related_products' => $relatedProducts,
        ]);
    }
}

