<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;


class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('is_admin', true)->first();

        $samples = [
            [
                'code' => 'BETATECH100K',
                'title' => 'Giảm 100.000đ',
                'description' => 'Áp dụng cho đơn laptop từ 10 triệu',
                'discount_type' => 'fixed',
                'discount_value' => 100_000,
                'min_order_amount' => 10_000_000,
                'max_discount' => null,
                'starts_at' => now(),
                'expires_at' => '2026-08-10 23:59:59',
                'usage_limit' => 100,
                'is_active' => true,
            ],
            [
                'code' => 'SALE5',
                'title' => 'Giảm 5% tối đa 500K',
                'description' => 'Đơn tối thiểu 5 triệu',
                'discount_type' => 'percent',
                'discount_value' => 5,
                'min_order_amount' => 5_000_000,
                'max_discount' => 500_000,
                'starts_at' => now(),
                'expires_at' => '2026-12-31 23:59:59',
                'usage_limit' => null,
                'is_active' => true,
            ],
            [
                'code' => 'FREESHIP50K',
                'title' => 'Giảm 50.000đ',
                'description' => 'Voucher nhỏ cho đơn từ 2 triệu',
                'discount_type' => 'fixed',
                'discount_value' => 50_000,
                'min_order_amount' => 2_000_000,
                'max_discount' => null,
                'starts_at' => now(),
                'expires_at' => '2026-06-30 23:59:59',
                'usage_limit' => 50,
                'is_active' => true,
            ],
        ];

        foreach ($samples as $data) {
            Voucher::updateOrCreate(
                ['code' => $data['code']],
                [...$data, 'created_by' => $admin?->id]
            );
        }
    }
}

