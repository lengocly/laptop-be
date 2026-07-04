<?php
//
//file này dùng để tìm kiếm sản phẩm theo ảnh

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VisionSearchService;
use Illuminate\Http\Request;
use RuntimeException;

class ImageSearchController extends Controller
{
    public function __construct(
        private VisionSearchService $vision,
    ) {}

    public function searchByImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // tối đa 5MB
        ]);

        try {
            $bytes = file_get_contents($request->file('image')->getRealPath());
            $keywords = $this->vision->extractKeywords($bytes);
            $products = $this->vision->searchProducts($keywords);

            return response()->json([
                'keywords' => $keywords,
                'products' => $products,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}