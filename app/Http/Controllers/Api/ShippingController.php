<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GhnShippingService;
use Illuminate\Http\Request;
use RuntimeException;

class ShippingController extends Controller
{
    public function __construct(
        private GhnShippingService $ghn,
    ) {}

    public function provinces()
    {
        try {
            $items = collect($this->ghn->getProvinces())->map(fn ($p) => [
                'id' => $p['ProvinceID'],
                'name' => $p['ProvinceName'],
            ])->values();

            return response()->json(['provinces' => $items]);
        } catch (RuntimeException $e) {
            return $this->ghnError($e);
        }
    }

    public function districts(Request $request)
    {
        $validated = $request->validate([
            'province_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $items = collect($this->ghn->getDistricts($validated['province_id']))
                ->map(fn ($d) => [
                    'id' => $d['DistrictID'],
                    'name' => $d['DistrictName'],
                ])->values();

            return response()->json(['districts' => $items]);
        } catch (RuntimeException $e) {
            return $this->ghnError($e);
        }
    }

    public function wards(Request $request)
    {
        $validated = $request->validate([
            'district_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $items = collect($this->ghn->getWards($validated['district_id']))
                ->map(fn ($w) => [
                    'code' => $w['WardCode'],
                    'name' => $w['WardName'],
                ])->values();

            return response()->json(['wards' => $items]);
        } catch (RuntimeException $e) {
            return $this->ghnError($e);
        }
    }

    public function calculateFee(Request $request)
    {
        $validated = $request->validate([
            'to_district_id' => ['required', 'integer', 'min:1'],
            'to_ward_code' => ['required', 'string', 'max:20'],
            'weight' => ['nullable', 'integer', 'min:1'],
            'insurance_value' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $fee = $this->ghn->calculateFee(
                $validated['to_district_id'],
                $validated['to_ward_code'],
                $validated['weight'] ?? config('ghn.default_weight'),
                $validated['insurance_value'] ?? 0,
            );

            return response()->json(['fee' => $fee]);
        } catch (RuntimeException $e) {
            return $this->ghnError($e);
        }
    }

    private function ghnError(RuntimeException $e)
    {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}
