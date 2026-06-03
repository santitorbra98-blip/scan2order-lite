<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RestaurantAndProductVisibilityTest extends TestCase
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
        ], $attributes));
    }

    public function test_superadmin_restaurant_listing_includes_creator_role(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $admin = $this->createUserWithRole('admin');

        Restaurant::create([
            'name' => 'Restaurante Admin',
            'address' => 'Calle Admin 1',
            'phone' => '111111111',
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Restaurant::create([
            'name' => 'Restaurante Superadmin',
            'address' => 'Calle Super 2',
            'phone' => '222222222',
            'active' => true,
            'created_by' => $superadmin->id,
        ]);

        Sanctum::actingAs($superadmin);

        $response = $this->getJson('/api/restaurants');

        $response->assertOk();

        $payload = $response->json('data');
        $this->assertCount(2, $payload);

        $byName = collect($payload)->keyBy('name');
        $this->assertSame('admin', $byName['Restaurante Admin']['creator']['role']);
        $this->assertSame('superadmin', $byName['Restaurante Superadmin']['creator']['role']);
    }

    public function test_superadmin_can_export_pdf_without_explicit_flag(): void
    {
        $superadmin = $this->createUserWithRole('superadmin', [
            'can_export_pdf' => false,
            'can_upload_images' => false,
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Menu PDF',
            'address' => 'Calle PDF 1',
            'phone' => '333333333',
            'active' => true,
            'created_by' => $superadmin->id,
        ]);

        Sanctum::actingAs($superadmin);

        $response = $this->get('/api/restaurants/' . $restaurant->id . '/catalogs/export-pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_uploaded_product_image_is_exposed_to_public_menu(): void
    {
        Storage::fake('public');

        $superadmin = $this->createUserWithRole('superadmin');
        $restaurant = Restaurant::create([
            'name' => 'Restaurante Fotos',
            'address' => 'Calle Imagen 1',
            'phone' => '444444444',
            'active' => true,
            'created_by' => $superadmin->id,
        ]);
        $catalog = Catalog::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Carta principal',
            'description' => null,
            'active' => true,
            'order' => 0,
        ]);
        $section = Section::create([
            'catalog_id' => $catalog->id,
            'name' => 'Entrantes',
            'description' => null,
            'active' => true,
            'order' => 0,
        ]);

        Sanctum::actingAs($superadmin);

        $upload = UploadedFile::fake()->image('plato.jpg', 800, 600);
        $createResponse = $this->post(
            '/api/restaurants/' . $restaurant->id . '/catalogs/' . $catalog->id . '/sections/' . $section->id . '/products',
            [
                'name' => 'Producto con foto',
                'description' => 'Descripción',
                'price' => '12.50',
                'active' => '1',
                'is_new' => '0',
                'image' => $upload,
            ]
        );

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.show_image', true);
        $this->assertNotEmpty($createResponse->json('data.image'));

        $publicResponse = $this->getJson('/api/restaurants/' . $restaurant->id . '/catalogs');

        $publicResponse->assertOk();
        $publicResponse->assertJsonPath('data.0.sections.0.products.0.show_image', true);
        $this->assertNotEmpty($publicResponse->json('data.0.sections.0.products.0.image'));
    }
}