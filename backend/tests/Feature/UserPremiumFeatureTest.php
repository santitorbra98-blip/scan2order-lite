<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPremiumFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        return User::create(array_merge([
            'name' => ucfirst($roleName) . ' User',
            'email' => $roleName . uniqid() . '@example.com',
            'phone' => null,
            'password' => 'password',
            'role_id' => $role->id,
            'status' => 'active',
            'can_upload_images' => false,
            'can_export_pdf' => false,
        ], $attributes));
    }

    public function test_admin_accounts_default_to_no_premium_image_access_on_create(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        Sanctum::actingAs($superadmin);

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $response = $this->postJson('/api/users', [
            'name' => 'Admin sin premium',
            'email' => 'no-premium-' . uniqid() . '@example.com',
            'phone' => null,
            'password' => 'Password12345!',
            'role_id' => $adminRole->id,
            'status' => 'active',
            'max_restaurants' => 5,
            'max_catalogs' => 20,
            'max_products' => null,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.can_upload_images', false);
        $response->assertJsonPath('data.can_export_pdf', false);
    }

    public function test_superadmin_can_unlock_and_lock_premium_features_for_admin_accounts(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        Sanctum::actingAs($superadmin);

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $createResponse = $this->postJson('/api/users', [
            'name' => 'Admin premium editable',
            'email' => 'toggle-' . uniqid() . '@example.com',
            'phone' => null,
            'password' => 'Password12345!',
            'role_id' => $adminRole->id,
            'status' => 'active',
            'max_restaurants' => 5,
            'max_catalogs' => 20,
            'max_products' => null,
        ]);

        $createResponse->assertCreated();
        $userId = $createResponse->json('data.id');

        $enableResponse = $this->putJson('/api/users/' . $userId, [
            'can_upload_images' => true,
            'can_export_pdf' => true,
        ]);

        $enableResponse->assertOk();
        $enableResponse->assertJsonPath('data.can_upload_images', true);
        $enableResponse->assertJsonPath('data.can_export_pdf', true);

        $disableResponse = $this->putJson('/api/users/' . $userId, [
            'can_upload_images' => false,
            'can_export_pdf' => false,
        ]);

        $disableResponse->assertOk();
        $disableResponse->assertJsonPath('data.can_upload_images', false);
        $disableResponse->assertJsonPath('data.can_export_pdf', false);
    }
}