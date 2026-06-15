<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

/**
 * Đơn hàng mẫu cho dashboard admin & demo báo cáo.
 * Chạy sau ProductSeeder + DemoUserSeeder.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'khach@betatech.com')->first();
        if (!$customer) {
            return;
        }

        $asus = Product::where('slug', 'asus-vivobook-15')->first();
        $dell = Product::where('slug', 'dell-inspiron-14')->first();
        $mac = Product::where('slug', 'macbook-pro-14')->first();
        $mouse = Product::where('slug', 'chuot-logitech-g102')->first();
        $sony = Product::where('slug', 'tai-nghe-sony-ch520')->first();

        if (!$asus || !$dell) {
            return;
        }

        $voucher = Voucher::where('code', 'BETATECH100K')->first();

        $samples = [
            [
                'order_code' => 'ORD-2506-00001',
                'status' => 'delivered',
                'payment_method' => 'cod',
                'payment_status' => 'paid',
                'shipping_fee' => 30_000,
                'voucher_discount' => 0,
                'note' => 'Giao giờ hành chính',
                'created_at' => now()->subDays(6),
                'items' => [
                    ['product' => $asus, 'qty' => 1, 'price' => 15_990_000],
                ],
            ],
            [
                'order_code' => 'ORD-2506-00002',
                'status' => 'delivered',
                'payment_method' => 'stripe',
                'payment_status' => 'paid',
                'shipping_fee' => 0,
                'voucher_discount' => 100_000,
                'voucher' => $voucher,
                'created_at' => now()->subDays(5),
                'items' => [
                    ['product' => $mac, 'qty' => 1, 'price' => 42_990_000],
                ],
            ],
            [
                'order_code' => 'ORD-2506-00003',
                'status' => 'shipping',
                'payment_method' => 'cod',
                'payment_status' => 'paid',
                'shipping_fee' => 35_000,
                'voucher_discount' => 0,
                'created_at' => now()->subDays(2),
                'items' => [
                    ['product' => $dell, 'qty' => 1, 'price' => 18_490_000],
                ],
            ],
            [
                'order_code' => 'ORD-2506-00004',
                'status' => 'processing',
                'payment_method' => 'cod',
                'payment_status' => 'paid',
                'shipping_fee' => 25_000,
                'voucher_discount' => 0,
                'created_at' => now()->subDay(),
                'items' => [
                    ['product' => $mouse, 'qty' => 2, 'price' => 390_000],
                    ['product' => $sony, 'qty' => 1, 'price' => 1_290_000],
                ],
            ],
            [
                'order_code' => 'ORD-2506-00005',
                'status' => 'pending',
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'shipping_fee' => 30_000,
                'voucher_discount' => 0,
                'note' => 'Gọi trước khi giao',
                'created_at' => now()->subHours(5),
                'items' => [
                    ['product' => $dell, 'qty' => 1, 'price' => 18_490_000],
                ],
            ],
            [
                'order_code' => 'ORD-2506-00006',
                'status' => 'cancelled',
                'payment_method' => 'cod',
                'payment_status' => 'unpaid',
                'shipping_fee' => 0,
                'voucher_discount' => 0,
                'admin_note' => 'Khách hủy qua điện thoại',
                'created_at' => now()->subDays(3),
                'items' => [
                    ['product' => $asus, 'qty' => 1, 'price' => 15_990_000],
                ],
            ],
        ];

        foreach ($samples as $data) {
            $itemsData = $data['items'];
            unset($data['items']);

            $subtotal = collect($itemsData)->sum(
                fn ($row) => $row['price'] * $row['qty']
            );

            $order = Order::updateOrCreate(
                ['order_code' => $data['order_code']],
                [
                    'user_id' => $customer->id,
                    'full_name' => $customer->name,
                    'phone' => '0901234567',
                    'address' => '34 Mai An Tiêm, Hai Bà Trưng, Hà Nội',
                    'note' => $data['note'] ?? null,
                    'admin_note' => $data['admin_note'] ?? null,
                    'subtotal' => $subtotal,
                    'shipping_fee' => $data['shipping_fee'] ?? 0,
                    'voucher_id' => ($data['voucher'] ?? null)?->id,
                    'voucher_discount' => $data['voucher_discount'] ?? 0,
                    'status' => $data['status'],
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $data['payment_status'],
                    'created_at' => $data['created_at'],
                    'updated_at' => $data['created_at'],
                ]
            );

            $order->items()->delete();

            foreach ($itemsData as $row) {
                $product = $row['product'];
                if (!$product) {
                    continue;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'product_name' => $product->name,
                    'option_label' => null,
                    'price' => $row['price'],
                    'quantity' => $row['qty'],
                    'line_total' => $row['price'] * $row['qty'],
                ]);
            }
        }
    }
}
