<?php
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
    public function extractKeywords(string $imageBytes): array
    {
        $credentials = config('google.credentials');
        if (!$credentials || !file_exists($credentials)) {
            throw new RuntimeException('Chưa cấu hình GOOGLE_APPLICATION_CREDENTIALS');
        }
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentials);
        $client = new ImageAnnotatorClient();
        $image = (new Image())->setContent($imageBytes);
        $features = [
            (new Feature())->setType(Type::LABEL_DETECTION),
            (new Feature())->setType(Type::LOGO_DETECTION),
            (new Feature())->setType(Type::TEXT_DETECTION),
        ];
        $request = (new AnnotateImageRequest())
            ->setImage($image)
            ->setFeatures($features);
        $batch = (new BatchAnnotateImagesRequest())
            ->setRequests([$request]);
        $response = $client->batchAnnotateImages($batch);
        $annotation = $response->getResponses()[0];
        if ($annotation->hasError()) {
            throw new RuntimeException($annotation->getError()->getMessage());
        }
        $keywords = [];
        foreach ($annotation->getLabelAnnotations() as $label) {
            $keywords[] = $label->getDescription();
        }
        foreach ($annotation->getLogoAnnotations() as $logo) {
            $keywords[] = $logo->getDescription();
        }
        $text = $annotation->getFullTextAnnotation()?->getText() ?? '';
        if ($text) {
            $keywords = array_merge($keywords, preg_split('/\s+/', $text));
        }
        $client->close();
        $keywords = array_unique(array_filter(array_map(
            fn ($k) => trim(strtolower($k)),
            $keywords
        )));
        return $this->cleanKeywords(array_values($keywords));
    }
    public function searchProducts(array $keywords, int $limit = 12): array
    {
        $keywords = $this->cleanKeywords($keywords);
        if (empty($keywords)) {
            return [];
        }
        $query = Product::query()->where('is_active', true);
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
        $products = $query->limit($limit)->get();
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
    private function brandKeywords(): array
    {
        return [
            'dell', 'hp', 'lenovo', 'asus', 'acer', 'msi', 'apple', 'macbook',
            'sony', 'jbl', 'logitech', 'razer', 'keychron', 'hyperx',
            'thinkpad', 'ideapad', 'vivobook', 'inspiron', 'pavilion',
        ];
    }
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
        $searchTerms = [];
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
        $searchTerms = array_values(array_unique($searchTerms));
        if (!empty($searchTerms)) {
            return $searchTerms;
        }
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