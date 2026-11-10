<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, bool $active = true): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $email,
            'password' => 'Password!123',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_operator_can_read_active_customers_without_user_management_access(): void
    {
        $operator = $this->user(User::ROLE_OPERATOR, 'operator-directory@cargotrace.test');
        $activeCustomer = $this->user(User::ROLE_CUSTOMER, 'active-directory@cargotrace.test');
        $this->user(User::ROLE_CUSTOMER, 'inactive-directory@cargotrace.test', false);

        Sanctum::actingAs($operator);
        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonFragment(['email' => $activeCustomer->email])
            ->assertJsonMissing(['email' => 'inactive-directory@cargotrace.test']);

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_customer_cannot_read_the_customer_directory(): void
    {
        $customer = $this->user(User::ROLE_CUSTOMER, 'customer-directory@cargotrace.test');
        Sanctum::actingAs($customer);

        $this->getJson('/api/customers')->assertForbidden();
    }
}
