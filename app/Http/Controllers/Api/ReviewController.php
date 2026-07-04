<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Lấy danh sách review và thống kê
    public function index(Product $product)
    {
        $reviews = ProductReview::with('user:id,name')
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        $stats = $this->buildStats($reviews);

        return response()->json([
            'stats' => $stats,
            'reviews' => $reviews->map(fn ($r) => $this->formatReview($r)),
        ]);
    }

    // Kiểm tra xem user có đủ điều kiện để đánh giá sản phẩm này không
    public function eligibility(Request $request, Product $product)
    {
        $user = $request->user();
        $existing = ProductReview::where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'can_review' => false,
                'reason' => 'already_reviewed',
                'message' => 'Bạn đã đánh giá sản phẩm này rồi.',
            ]);
        }

        $hasPurchased = $this->userHasPurchased($user->id, $product->id);

        if (!$hasPurchased) {
            return response()->json([
                'can_review' => false,
                'reason' => 'not_purchased',
                'message' => 'Chỉ khách đã mua và nhận hàng mới có thể đánh giá sản phẩm này.',
            ]);
        }

        return response()->json([
            'can_review' => true,
            'reason' => null,
            'message' => null,
        ]);
    }

    // Tạo review
    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        if (ProductReview::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Bạn đã đánh giá sản phẩm này rồi.',
            ], 422);
        }

        if (!$this->userHasPurchased($user->id, $product->id)) {
            return response()->json([
                'message' => 'Chỉ khách đã mua và nhận hàng mới có thể đánh giá sản phẩm này.',
            ], 403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'content' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $review = ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_verified_purchase' => true,
        ]);

        $review->load('user:id,name');

        return response()->json([
            'message' => 'Đánh giá của bạn đã được gửi thành công!',
            'review' => $this->formatReview($review),
        ], 201);
    }

    // Kiểm tra xem user đã mua sản phẩm này chưa
    private function userHasPurchased(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)
            ->where('fulfillment_status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    // Tính toán thống kê review
    private function buildStats($reviews): array
    {
        $total = $reviews->count();
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($reviews as $review) {
            $distribution[$review->rating] = ($distribution[$review->rating] ?? 0) + 1;
        }

        $average = $total > 0
            ? round($reviews->avg('rating'), 1)
            : 0;

        $fourPlus = $distribution[5] + $distribution[4];
        $verified = $reviews->where('is_verified_purchase', true)->count();
        $satisfiedPercent = $total > 0
            ? (int) round(($fourPlus / $total) * 100)
            : 0;

        return [
            'average' => $average,
            'total' => $total,
            'distribution' => $distribution,
            'four_plus_count' => $fourPlus,
            'verified_count' => $verified,
            'satisfied_percent' => $satisfiedPercent,
        ];
    }

    // Định dạng review cho frontend
    private function formatReview(ProductReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'content' => $review->content,
            'is_verified_purchase' => $review->is_verified_purchase,
            'created_at' => $review->created_at,
            'user' => [
                'id' => $review->user->id,
                'name' => $review->user->name,
            ],
        ];
    }
}
