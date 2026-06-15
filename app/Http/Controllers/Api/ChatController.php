<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;

class ChatController extends Controller
{
    // ─── 1. Cấu hình cơ bản ─────────────────────────────────────────

    private function suggestions(): array
    {
        return [
            'Tìm laptop gaming',
            'Laptop đi học',
            'Cách đặt hàng',
            'Chính sách đổi trả',
        ];
    }

    private function jsonOk(string $reply, array $products = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'reply'       => $reply,
            'suggestions' => $this->suggestions(),
            'products'    => $products,
        ]);
    }

    // ─── 2. Nhớ tên khách từ lịch sử chat ─────────────────────────────

    private function findCustomerName(array $messages): ?string
    {
        foreach ($messages as $msg) {
            if ($msg['role'] !== 'user') {
                continue;
            }

            // "tôi tên linh" / "tên tôi là Dũng" / "mình tên là Hà"
            if (preg_match('/(?:tôi|toi|mình|minh|em)\s+tên\s+(?:là|la)?\s*([\p{L}]+)/ui', $msg['text'], $m)) {
                return $this->formatName($m[1]);
            }
            if (preg_match('/tên\s+(?:tôi|toi|em)\s+(?:là|la)\s*([\p{L}]+)/ui', $msg['text'], $m)) {
                return $this->formatName($m[1]);
            }
        }

        return null;
    }

    private function formatName(string $name): string
    {
        return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
    }

    // ─── 3. Trả lời nhanh — KHÔNG cần Gemini ─────────────────────────

    /** FAQ: bấm chip hoặc gõ đúng cụm */
    private function faqReply(string $text): ?string
    {
        $t = mb_strtolower(trim($text));

        $faqs = [
            'chính sách đổi trả' => 'BetaTech hỗ trợ đổi trả 14 ngày (lỗi NSX) hoặc 30 ngày (tùy sản phẩm). '
                . 'Hàng còn seal, đủ phụ kiện. Liên hệ hotline trên web để được hướng dẫn ạ.',
            'cách đặt hàng' => 'Chọn sản phẩm → Thêm giỏ → Thanh toán (COD hoặc Stripe). '
                . 'Đơn từ 10.000.000₫ miễn phí ship. Cần đặt hộ, gọi hotline BetaTech ạ.',
            'xem khuyến mãi' => 'Khuyến mãi cập nhật tại trang chủ. '
                . 'Anh/chị thử bấm "Laptop đi học" hoặc "Tìm laptop gaming" để xem SP ạ.',
        ];

        foreach ($faqs as $key => $answer) {
            if (str_contains($t, $key)) {
                return $answer;
            }
        }

        return null;
    }

    /** Chào hỏi / giới thiệu tên */
    private function chatReply(string $text, ?string $name): ?string
    {
        $t = mb_strtolower(trim($text));

        // Vừa nói tên
        if (preg_match('/(?:tôi|toi|mình|minh|em)\s+tên\s+(?:là|la)?\s*([\p{L}]+)/ui', $text, $m)) {
            $n = $this->formatName($m[1]);
            return "Dạ chào {$n}! Rất vui được hỗ trợ. Anh/chị cần tư vấn laptop gaming, đi học hay phụ kiện ạ?";
        }

        // Chào ngắn: "chào e", "xin chào", "hello"
        $greetings = ['chào', 'chao', 'hello', 'hi', 'hey', 'xin chào', 'xin chao'];
        foreach ($greetings as $g) {
            if ($t === $g || str_starts_with($t, $g . ' ') || str_starts_with($t, $g)) {
                if (mb_strlen($t) <= 25) {
                    return $name
                        ? "Dạ chào {$name}! Em giúp gì thêm cho anh/chị hôm nay ạ?"
                        : 'Dạ chào anh/chị! Em là trợ lý BetaTech. Cần tư vấn laptop, phụ kiện hay đơn hàng ạ?';
                }
            }
        }

        return null;
    }

    /** Hỏi lệch chủ đề — shop không bán */
    private function isOffTopic(string $text): bool
    {
        if ($this->wantsProducts($text)) {
            return false;
        }

        $t = mb_strtolower(trim($text));
        $offTopic = [
            'quần áo', 'quan ao', 'quần', 'quan', 'váy', 'vay', 'áo khoác', 'thời trang',
            'giày', 'giay', 'dép', 'dep', 'túi xách', 'balo thời trang',
            'điện thoại', 'dien thoai', 'iphone', 'ipad', 'tablet',
            'nội thất', 'noi that', 'tủ lạnh', 'máy giặt', 'bàn ăn',
            'xe máy', 'xe may', 'ô tô', 'o to', 'xe hơi',
            'mỹ phẩm', 'my pham', 'son môi', 'nước hoa',
            'thực phẩm', 'đồ ăn', 'do an', 'nhà hàng',
            'nhà đất', 'căn hộ', 'can ho', 'vé máy bay', 'du lịch', 'du lich',
            'vàng', 'trang sức', 'trang suc',
        ];

        foreach ($offTopic as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    /** Trả lời khi hỏi ngoài ngành hàng shop */
    private function offTopicReply(string $text, ?string $name): string
    {
        $t   = mb_strtolower(trim($text));
        $hi  = $name ? " {$name}" : '';
        $ask = 'mặt hàng đó';

        if (str_contains($t, 'quần') || str_contains($t, 'áo') || str_contains($t, 'thời trang')) {
            $ask = 'quần áo / thời trang';
        } elseif (str_contains($t, 'giày') || str_contains($t, 'dép')) {
            $ask = 'giày dép';
        } elseif (str_contains($t, 'điện thoại') || str_contains($t, 'iphone')) {
            $ask = 'điện thoại';
        } elseif (str_contains($t, 'ô tô') || str_contains($t, 'o to') || str_contains($t, 'xe')) {
            $ask = 'xe cộ';
        } elseif (str_contains($t, 'mỹ phẩm') || str_contains($t, 'son')) {
            $ask = 'mỹ phẩm';
        }

        return "Dạ chào{$hi}! BetaTech chuyên laptop chính hãng và phụ kiện công nghệ "
            . "(chuột, bàn phím, tai nghe). Shop em chưa bán {$ask} ạ. "
            . "Anh/chị xem gợi ý sản phẩm bên dưới, hoặc bấm \"Laptop đi học\" / \"Tìm laptop gaming\" nhé!";
    }

    /** User hỏi shop bán gì */
    private function isShopQuestion(string $text): bool
    {
        $t = mb_strtolower(trim($text));
        $phrases = [
            'bán gì', 'ban gi', 'shop bán', 'cửa hàng bán', 'betatech là gì',
            'beta tech là gì', 'shop mình', 'shop em', 'có những gì', 'ngành hàng',
        ];

        foreach ($phrases as $p) {
            if (str_contains($t, $p)) {
                return true;
            }
        }

        return false;
    }

    private function shopIntroReply(?string $name): string
    {
        $hi = $name ? " {$name}" : '';

        return "Dạ chào{$hi}! BetaTech bán laptop chính hãng (ASUS, Dell, MacBook, HP, Lenovo…) "
            . "và phụ kiện: chuột, bàn phím, tai nghe. Giao hàng toàn quốc, đổi trả 14–30 ngày. "
            . "Anh/chị xem sản phẩm nổi bật bên dưới ạ!";
    }

    private function featuredProducts(int $limit = 4)
    {
        return Product::where('is_active', true)->inRandomOrder()->limit($limit)->get();
    }


    /** Câu user → từ khóa tìm tên SP */
    private function searchKeywords(string $text): array
    {
        $t = mb_strtolower(trim($text));

        $map = [
            'laptop gaming'     => ['gaming', 'razer'],
            'tìm laptop gaming' => ['gaming', 'razer'],
            'laptop đi học'     => ['vivobook', 'ideapad', 'inspiron', 'slim'],
            'đi học'            => ['vivobook', 'ideapad', 'inspiron', 'pavilion', 'slim'],
            'sinh viên'         => ['vivobook', 'ideapad', 'inspiron'],
            'văn phòng'         => ['vivobook', 'ideapad', 'inspiron'],
            'tai nghe'          => ['tai nghe', 'sony', 'jbl'],
            'chuột'             => ['chuột', 'logitech', 'razer'],
            'bàn phím'          => ['bàn phím', 'keychron'],
            'laptop'            => ['vivobook', 'macbook', 'inspiron', 'ideapad', 'pavilion'],
        ];

        foreach ($map as $phrase => $keys) {
            if (str_contains($t, $phrase)) {
                return $keys;
            }
        }

        return [];
    }

    /** User có đang hỏi về sản phẩm không? */
    private function wantsProducts(string $text): bool
    {
        if ($this->searchKeywords($text) !== []) {
            return true;
        }

        $t = mb_strtolower($text);
        $words = [
            'laptop', 'macbook', 'gaming', 'mua', 'tìm', 'giá', 'mẫu', 'gợi ý',
            'phù hợp', 'sản phẩm', 'tư vấn', 'học', 'sinh viên', 'tai nghe', 'chuột',
        ];

        foreach ($words as $w) {
            if (str_contains($t, $w)) {
                return true;
            }
        }

        return (bool) preg_match('/có\s+.+\s+nào/u', $t);
    }

    private function findProducts(string $text, int $limit = 4)
    {
        $keys = $this->searchKeywords($text);

        if ($keys !== []) {
            $found = Product::where('is_active', true)
                ->where(function ($q) use ($keys) {
                    foreach ($keys as $k) {
                        $q->orWhere('name', 'like', '%' . $k . '%');
                    }
                })
                ->limit($limit)
                ->get();

            if ($found->isNotEmpty()) {
                return ['products' => $found, 'fallback' => false];
            }
        }

        // Không khớp → lấy SP gợi ý
        return [
            'products' => Product::where('is_active', true)->inRandomOrder()->limit($limit)->get(),
            'fallback' => true,
        ];
    }

    private function toProductJson($products): array
    {
        return $products->map(fn ($p) => [
            'id'    => $p->id,
            'name'  => $p->name,
            'price' => $p->price_display,
            'image' => asset('storage/' . $p->image_main),
        ])->values()->all();
    }

    /** Câu trả lời kèm danh sách SP */
    private function productReply(string $text, ?string $name, bool $fallback): string
    {
        $t = mb_strtolower($text);
        $hi  = $name ? " {$name}" : '';

        if (str_contains($t, 'học') || str_contains($t, 'sinh viên') || str_contains($t, 'laptop đi học')) {
            return "Dạ chào{$hi}! Em gợi ý laptop nhẹ, pin tốt phù hợp đi học bên dưới ạ.";
        }
        if (str_contains($t, 'gaming')) {
            return "Dạ chào{$hi}! Các mẫu phù hợp gaming anh/chị tham khảo bên dưới ạ.";
        }
        if ($fallback) {
            return "Dạ chào{$hi}! Em gợi ý thêm vài sản phẩm hot bên dưới ạ.";
        }

        return "Dạ chào{$hi}! Em tìm thấy sản phẩm phù hợp bên dưới ạ.";
    }

    // ─── 5. Gemini — chỉ dùng khi câu hỏi phức tạp ───────────────────

    private function askGemini(string $apiKey, string $model, array $contents, ?string $name): ?string
    {
        $nameHint = $name ? " Tên khách: {$name}." : '';

        try {
            // API key qua header — không để lộ trên URL/log
            $response = Http::timeout(20)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'system_instruction' => [
                            'parts' => [[
                                'text' => 'Bạn là trợ lý BetaTech bán laptop & phụ kiện. '
                                    . 'Trả lời tiếng Việt, ngắn gọn, lịch sự.' . $nameHint
                                    . ' KHÔNG bịa tên/giá sản phẩm. Gợi khách bấm chip bên dưới nếu cần xem SP.',
                            ]],
                        ],
                        'contents' => $contents,
                    ]
                );

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text');
            }
        } catch (\Throwable $e) {
            // bỏ qua — dùng câu local bên dưới
        }

        return null;
    }

    // ─── 6. API chính ────────────────────────────────────────────────

    public function send(Request $request)
    {
        $validated = $request->validate([
            'messages'          => 'required|array|min:1|max:30',
            'messages.*.role'   => 'required|in:user,model',
            'messages.*.text'   => 'required|string|max:2000',
        ]);

        $messages     = $validated['messages'];
        $lastUserText = collect($messages)->where('role', 'user')->last()['text'] ?? '';
        $name         = $this->findCustomerName($messages);

        // A) FAQ → trả ngay
        if ($faq = $this->faqReply($lastUserText)) {
            return $this->jsonOk($faq);
        }

        // B) Chào / nói tên → trả ngay
        if ($chat = $this->chatReply($lastUserText, $name)) {
            return $this->jsonOk($chat);
        }

        // C) Hỏi sản phẩm → DB + câu trả lời (không phụ thuộc Gemini)
        if ($this->wantsProducts($lastUserText)) {
            $result   = $this->findProducts($lastUserText);
            $products = $this->toProductJson($result['products']);
            $reply    = $this->productReply($lastUserText, $name, $result['fallback']);

            return $this->jsonOk($reply, $products);
        }

        // C2) Hỏi shop bán gì → giới thiệu + SP nổi bật
        if ($this->isShopQuestion($lastUserText)) {
            return $this->jsonOk(
                $this->shopIntroReply($name),
                $this->toProductJson($this->featuredProducts())
            );
        }

        // C3) Lệch chủ đề (quần áo, xe, mỹ phẩm…) → giải thích shop + gợi ý SP
        if ($this->isOffTopic($lastUserText)) {
            return $this->jsonOk(
                $this->offTopicReply($lastUserText, $name),
                $this->toProductJson($this->featuredProducts())
            );
        }

        // D) Câu khác → thử Gemini, không được thì trả lời gợi ý chip
        $apiKey = config('services.gemini.key');
        $model  = config('services.gemini.model', 'gemini-2.5-flash');

        if (!$apiKey) {
            return response()->json(['message' => 'Chưa cấu hình GEMINI_API_KEY'], 500);
        }

        $contents = collect($messages)
            ->filter(fn ($msg, $i) => !($i === 0 && $msg['role'] === 'model'))
            ->filter(fn ($msg) => !str_contains($msg['text'], 'Hệ thống đang bận'))
            ->map(fn ($msg) => ['role' => $msg['role'], 'parts' => [['text' => $msg['text']]]])
            ->values()
            ->take(-8)
            ->all();

        $reply = $this->askGemini($apiKey, $model, $contents, $name);

        if (!$reply) {
            $hi = $name ? " {$name}" : '';

            if ($this->isOffTopic($lastUserText)) {
                $reply = $this->offTopicReply($lastUserText, $name);
                $products = $this->toProductJson($this->featuredProducts());

                return $this->jsonOk($reply, $products);
            }

            $reply = "Dạ chào{$hi}! BetaTech chuyên laptop & phụ kiện công nghệ. "
                . "Anh/chị thử bấm gợi ý bên dưới hoặc gõ \"laptop đi học\", \"laptop gaming\" nhé ạ.";
        }

        return $this->jsonOk($reply);
    }
}
