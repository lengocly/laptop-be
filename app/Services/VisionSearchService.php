<?php
//file này dùng để phân tích ảnh và trả về các từ khóa

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use RuntimeException;
use App\Models\Product;

class VisionSearchService
{
    /** Phân tích ảnh → trả mảng từ khóa */
    public function extractKeywords(string $imageBytes): array
    {
        $credentials = config('google.credentials');
        if (!$credentials || !file_exists($credentials)) {
            throw new RuntimeException('Chưa cấu hình GOOGLE_APPLICATION_CREDENTIALS');
        }

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentials);

        //tạo client để phân tích ảnh
        $client = new ImageAnnotatorClient();

        //tạo ảnh từ bytes
        $image = (new Image())->setContent($imageBytes);

        //tạo các tính năng để phân tích ảnh
        $features = [
            (new Feature())->setType(Type::LABEL_DETECTION),
            (new Feature())->setType(Type::LOGO_DETECTION),
            (new Feature())->setType(Type::TEXT_DETECTION),
        ];

        //tạo request để phân tích ảnh
        $request = (new AnnotateImageRequest())
            ->setImage($image)
            ->setFeatures($features);

        //tạo batch để phân tích nhiều ảnh
        $batch = (new BatchAnnotateImagesRequest())
            ->setRequests([$request]);

        //phân tích ảnh
        $response = $client->batchAnnotateImages($batch);
        $annotation = $response->getResponses()[0];

        //kiểm tra lỗi
        if ($annotation->hasError()) {
            throw new RuntimeException($annotation->getError()->getMessage());
        }

        //tạo mảng từ khóa
        $keywords = [];

        // Labels: Laptop, Electronics...
        //lấy các từ khóa từ labels
        foreach ($annotation->getLabelAnnotations() as $label) {
            $keywords[] = $label->getDescription();
        }

        // Logo: Dell, Apple...
        //lấy các từ khóa từ logos
        foreach ($annotation->getLogoAnnotations() as $logo) {
            $keywords[] = $logo->getDescription();
        }

        // OCR: chữ trên ảnh
        $text = $annotation->getFullTextAnnotation()?->getText() ?? '';
        if ($text) {
            $keywords = array_merge($keywords, preg_split('/\s+/', $text));
        }

        $client->close();

        // Làm sạch, bỏ trùng
        $keywords = array_unique(array_filter(array_map(
            fn ($k) => trim(strtolower($k)),
            $keywords
        )));

        return $this->cleanKeywords(array_values($keywords));
    }

    //tìm sản phẩm theo các từ khóa
    public function searchProducts(array $keywords, int $limit = 12): array
    {
        $keywords = $this->cleanKeywords($keywords);

        if (empty($keywords)) {
            return [];
        }

        //tạo query để tìm sản phẩm
        $query = Product::query()->where('is_active', true);

        //tìm sản phẩm theo các từ khóa
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $word) {
                $q->orWhere('name', 'like', "%{$word}%");

                if (\Schema::hasColumn('products', 'cpu')) {
                    $q->orWhere('cpu', 'like', "%{$word}%");
                }

                if (\Schema::hasColumn('products', 'ram')) {
                    $q->orWhere('ram', 'like', "%{$word}%");
                }
            }
        });

        //lấy sản phẩm theo query
        $products = $query->limit($limit)->get();

        //định dạng sản phẩm
        return $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price_display ?? $p->price,
                'price_original' => $p->price_original ?? null,
                'images' => array_values(array_filter([
                    $p->image_main ? asset('storage/' . $p->image_main) : null,
                    $p->image_hover ? asset('storage/' . $p->image_hover) : null,
                ])),
            ];
        })->values()->all();
    }

    /** Map label Vision (EN) → từ khóa khớp tên SP trong DB (VI) */
    //ánh xạ từ khóa Vision sang từ khóa sản phẩm
    private function categoryMap(): array
    {
        return [
            'headphone' => 'Tai nghe',
            'headphones' => 'Tai nghe',
            'headset' => 'Tai nghe',
            'earphone' => 'Tai nghe',
            'earphones' => 'Tai nghe',
            'earbud' => 'Tai nghe',
            'earbuds' => 'Tai nghe',
            'headphones accessory' => 'Tai nghe',
            'mouse' => 'Chuột',
            'computer mouse' => 'Chuột',
            'pointing device' => 'Chuột',
            'keyboard' => 'Bàn phím',
            'keypad' => 'Bàn phím',
            'laptop' => 'Laptop',
            'notebook' => 'Laptop',
            'netbook' => 'Laptop',
            'macbook' => 'MacBook',
            'personal computer' => 'Laptop',
        ];
    }

    //từ khóa thương hiệu
    private function brandKeywords(): array
    {
        return [
            'dell', 'hp', 'lenovo', 'asus', 'acer', 'msi', 'apple', 'macbook',
            'sony', 'jbl', 'logitech', 'razer', 'keychron', 'hyperx',
            'thinkpad', 'ideapad', 'vivobook', 'inspiron', 'pavilion',
        ];
    }

    //làm sạch các từ khóa, bỏ trùng, bỏ stop words
    private function cleanKeywords(array $keywords): array
    {
        $stopWords = [
            'electronic device',
            'gadget',
            'display device',
            'communication device',
            'technology',
            'portable communications device',
            'office supplies',
            'office equipment',
            'silver',
            'input device',
            'device',
            'computer hardware',
            'product',
            'material',
            'font',
        ];

        //tạo mảng từ khóa tìm kiếm
        $searchTerms = [];

        //tìm kiếm sản phẩm theo các từ khóa
        foreach ($keywords as $keyword) {
            $keyword = trim(strtolower($keyword));

            if (strlen($keyword) < 2 || in_array($keyword, $stopWords)) {
                continue;
            }

            foreach ($this->categoryMap() as $pattern => $vietnamese) {
                if ($keyword === $pattern || str_contains($keyword, $pattern)) {
                    $searchTerms[] = $vietnamese;
                }
            }

            foreach ($this->brandKeywords() as $brand) {
                if (str_contains($keyword, $brand)) {
                    $searchTerms[] = $brand;
                }
            }
        }

        //làm sạch từ khóa, bỏ trùng
        $searchTerms = array_values(array_unique($searchTerms));

        //nếu có từ khóa, trả về từ khóa
        if (!empty($searchTerms)) {
            return $searchTerms;
        }

        //nếu không có từ khóa, dùng label Vision gốc (không ép laptop)
        foreach (array_slice($keywords, 0, 6) as $keyword) {
            $keyword = trim(strtolower($keyword));
            if (strlen($keyword) >= 3 && !in_array($keyword, $stopWords)) {
                $searchTerms[] = $keyword;
                if (count($searchTerms) >= 3) {
                    break;
                }
            }
        }

        return array_values(array_unique($searchTerms));
    }
}