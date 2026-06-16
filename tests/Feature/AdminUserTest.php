<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_users(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_users(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_admin_can_list_and_search_users(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        User::factory()->create(['name' => 'Nguyen Van A', 'email' => 'a@test.com']);
        User::factory()->create(['name' => 'Tran Thi B', 'email' => 'b@test.com']);

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'is_admin', 'orders_count'],
                ],
            ]);

        $this->getJson('/api/v1/admin/users?keyword=nguyen')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_view_user_detail_with_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $customer = User::factory()->create(['name' => 'Khach Hang']);

        Order::create([
            'user_id' => $customer->id,
            'order_status' => 'open',
            'fulfillment_status' => 'unfulfilled',
            'full_name' => 'Khach Hang',
            'phone' => '0901234567',
            'address' => 'Ha Noi',
            'items_subtotal' => 1_000_000,
            'subtotal' => 1_000_000,
            'shipping_fee' => 0,
            'order_code' => 'BT-' . uniqid(),
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);

        $this->getJson("/api/v1/admin/users/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'Khach Hang')
            ->assertJsonPath('user.orders_count', 1)
            ->assertJsonPath('total_spent', 1_000_000)
            ->assertJsonCount(1, 'orders');
    }
}
