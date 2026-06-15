<?php

//file này dùng để tính phí vận chuyển bằng GHN

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GhnShippingService
{
    /** Header dùng cho mọi request GHN */
    private function headers(bool $withShop = false): array
    {
        $headers = [
            'Token' => config('ghn.token'),
            'Content-Type' => 'application/json',
        ];

        if ($withShop) {
            $headers['ShopId'] = (string) config('ghn.shop_id');
        }

        return $headers;
    }

    /** Gọi GHN và kiểm tra code === 200 */
    private function request(string $method, string $path, array $payload = [], bool $withShop = false): array
    {
        $token = config('ghn.token');
        if (empty($token)) {
            throw new RuntimeException('GHN_TOKEN chưa cấu hình trong .env');
        }

        $url = config('ghn.base_url') . $path;
        $client = Http::withHeaders($this->headers($withShop))->timeout(20);

        $response = $method === 'get'
            ? $client->get($url, $payload)
            : $client->post($url, $payload);

        return $this->parseResponse($response);
    }

    private function parseResponse(Response $response): array
    {
        $json = $response->json();

        if (!$response->ok() || ($json['code'] ?? 0) !== 200) {
            throw new RuntimeException($json['message'] ?? 'GHN API lỗi');
        }

        return $json['data'] ?? [];
    }

    /** Danh sách Tỉnh/TP */
    public function getProvinces(): array
    {
        return $this->request('get', '/shiip/public-api/master-data/province');
    }

    /** Danh sách Quận/Huyện theo tỉnh */
    public function getDistricts(int $provinceId): array
    {
        return $this->request('get', '/shiip/public-api/master-data/district', [
            'province_id' => $provinceId,
        ]);
    }

    /** Danh sách Phường/Xã theo quận */
    public function getWards(int $districtId): array
    {
        return $this->request('get', '/shiip/public-api/master-data/ward', [
            'district_id' => $districtId,
        ]);
    }

    /** Tính phí vận chuyển (VNĐ) */
    public function calculateFee(
        int $toDistrictId,
        string $toWardCode,
        int $weightGram,
        int $insuranceValue = 0
    ): int {
        $data = $this->request('post', '/shiip/public-api/v2/shipping-order/fee', [
            'service_type_id' => 2, // hàng nhẹ
            'from_district_id' => config('ghn.from_district_id'),
            'from_ward_code' => config('ghn.from_ward_code'),
            'to_district_id' => $toDistrictId,
            'to_ward_code' => $toWardCode,
            'weight' => max($weightGram, 1),
            'length' => 30,
            'width' => 25,
            'height' => 10,
            'insurance_value' => max($insuranceValue, 0),
            'coupon' => null,
        ], withShop: true);

        // GHN có thể trả total hoặc service_fee — lấy cái có giá trị
        return (int) ($data['total'] ?? $data['service_fee'] ?? 0);
    }
}