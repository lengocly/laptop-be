<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /** Gộp các dòng trùng product_id + variant_id trước khi trừ/hoàn kho. */
    public static function aggregateLineItems(array|Collection $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $productId = (int) $row['product_id'];
            $variantId = isset($row['product_variant_id']) && $row['product_variant_id']
                ? (int) $row['product_variant_id']
                : null;
            $key = $productId . ':' . ($variantId ?? 'base');

            if (!isset($map[$key])) {
                $map[$key] = [
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'product_name' => $row['product_name'] ?? 'Sản phẩm',
                    'option_label' => $row['option_label'] ?? null,
                    'price' => (int) ($row['price'] ?? 0),
                    'quantity' => (int) ($row['quantity'] ?? 0),
                ];
            } else {
                $map[$key]['quantity'] += (int) ($row['quantity'] ?? 0);
            }
        }

        return array_values($map);
    }

    public function reserveForOrder(Order $order, array $items): void
    {
        foreach (self::aggregateLineItems($items) as $item) {
            $this->adjustStock(
                productId: (int) $item['product_id'],
                variantId: $item['product_variant_id'] ?? null,
                orderId: $order->id,
                quantity: (int) $item['quantity'],
                type: InventoryTransactionType::Reserve,
                idempotencyKey: "order:{$order->id}:reserve:{$item['product_id']}:" . ($item['product_variant_id'] ?? 'base'),
                direction: -1,
                productName: $item['product_name'] ?? 'Sản phẩm',
                allowInactive: false,
            );
        }
    }

    /** Hoàn kho idempotent — mỗi đơn chỉ release một lần. */
    public function releaseForOrder(Order $order): bool
    {
        if ($order->inventory_released_at) {
            return false;
        }

        $order->loadMissing('items');
        $released = false;

        DB::transaction(function () use ($order, &$released) {
            $fresh = Order::lockForUpdate()->find($order->id);

            if (!$fresh || $fresh->inventory_released_at) {
                return;
            }

            foreach (self::aggregateLineItems($fresh->items) as $item) {
                $key = "order:{$fresh->id}:release:{$item['product_id']}:" . ($item['product_variant_id'] ?? 'base');

                if (InventoryTransaction::where('idempotency_key', $key)->exists()) {
                    continue;
                }

                $this->adjustStock(
                    productId: (int) $item['product_id'],
                    variantId: $item['product_variant_id'] ?? null,
                    orderId: $fresh->id,
                    quantity: (int) $item['quantity'],
                    type: InventoryTransactionType::Release,
                    idempotencyKey: $key,
                    direction: 1,
                    productName: $item['product_name'],
                    allowInactive: true,
                );
            }

            $fresh->update(['inventory_released_at' => now()]);
            $released = true;
        });

        return $released;
    }

    private function adjustStock(
        int $productId,
        ?int $variantId,
        int $orderId,
        int $quantity,
        InventoryTransactionType $type,
        string $idempotencyKey,
        int $direction,
        string $productName,
        bool $allowInactive,
    ): void {
        if (InventoryTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $productQuery = $allowInactive ? Product::withTrashed() : Product::query();
        $product = $productQuery->lockForUpdate()->find($productId);

        if (!$product) {
            if ($allowInactive) {
                // SP đã xóa — ghi log transaction nhưng không cộng kho
                InventoryTransaction::create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'order_id' => $orderId,
                    'type' => $type->value,
                    'quantity' => $quantity,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => ['skipped' => 'product_deleted'],
                    'created_at' => now(),
                ]);
                return;
            }
            $this->fail("Sản phẩm \"{$productName}\" không còn khả dụng.");
        }

        if (!$allowInactive && !$product->is_active) {
            $this->fail("Sản phẩm \"{$productName}\" không còn khả dụng.");
        }

        $hasVariants = $product->variants()->withTrashed()->exists();

        if ($hasVariants && !$variantId) {
            $this->fail("Vui lòng chọn cấu hình cho sản phẩm \"{$productName}\".");
        }

        if ($variantId) {
            $variantQuery = $allowInactive
                ? ProductVariant::withTrashed()
                : ProductVariant::query();

            $variant = $variantQuery->lockForUpdate()
                ->where('product_id', $product->id)
                ->where('id', $variantId)
                ->first();

            if (!$variant) {
                if ($allowInactive) {
                    InventoryTransaction::create([
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'order_id' => $orderId,
                        'type' => $type->value,
                        'quantity' => $quantity,
                        'idempotency_key' => $idempotencyKey,
                        'meta' => ['skipped' => 'variant_deleted'],
                        'created_at' => now(),
                    ]);
                    return;
                }
                $this->fail('Biến thể sản phẩm không hợp lệ hoặc đã ngừng bán.');
            }

            if (!$allowInactive && !$variant->is_active) {
                $this->fail('Biến thể sản phẩm không hợp lệ hoặc đã ngừng bán.');
            }

            if ($direction < 0 && $variant->stock < $quantity) {
                $this->fail("Sản phẩm \"{$productName}\" chỉ còn {$variant->stock} sản phẩm.");
            }

            $variant->increment('stock', $direction * $quantity);
            $targetVariantId = $variant->id;
        } else {
            if ($direction < 0 && $product->stock < $quantity) {
                $this->fail("Sản phẩm \"{$productName}\" chỉ còn {$product->stock} sản phẩm.");
            }

            $product->increment('stock', $direction * $quantity);
            $targetVariantId = null;
        }

        InventoryTransaction::create([
            'product_id' => $productId,
            'product_variant_id' => $targetVariantId,
            'order_id' => $orderId,
            'type' => $type->value,
            'quantity' => $quantity,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);
    }

    private function fail(string $message): void
    {
        throw new HttpResponseException(response()->json(['message' => $message], 422));
    }
}
