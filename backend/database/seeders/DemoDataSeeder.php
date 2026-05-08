<?php

namespace Database\Seeders;

use App\Models\Catalog;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles (ensure they exist) ──────────────────────────────────────
        $this->call(RolePermissionSeeder::class);

        $superadminRole = Role::where('name', 'superadmin')->first();
        $adminRole      = Role::where('name', 'admin')->first();

        // ── Superadmin user ────────────────────────────────────────────────
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@scan2order.test'],
            [
                'name'              => 'Super Admin',
                'password'          => Hash::make('superadmin1234!'),
                'role_id'           => $superadminRole->id,
                'status'            => 'active',
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
            ]
        );

        // ── Admin user ─────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@scan2order.test'],
            [
                'name'              => 'Admin Demo',
                'password'          => Hash::make('admin1234567!'),
                'role_id'           => $adminRole->id,
                'status'            => 'active',
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
                'max_restaurants'   => 5,
                'max_catalogs'      => 20,
                'max_products'      => null,
            ]
        );

        $schedule = [
            'monday'    => ['enabled' => true,  'open' => '09:00', 'close' => '23:00'],
            'tuesday'   => ['enabled' => true,  'open' => '09:00', 'close' => '23:00'],
            'wednesday' => ['enabled' => true,  'open' => '09:00', 'close' => '23:00'],
            'thursday'  => ['enabled' => true,  'open' => '09:00', 'close' => '23:00'],
            'friday'    => ['enabled' => true,  'open' => '09:00', 'close' => '00:00'],
            'saturday'  => ['enabled' => true,  'open' => '10:00', 'close' => '00:00'],
            'sunday'    => ['enabled' => false, 'open' => '10:00', 'close' => '22:00'],
        ];

        // ══════════════════════════════════════════════════════════════════
        // RESTAURANTE 1 — La Trattoria
        // ══════════════════════════════════════════════════════════════════
        $trattoria = Restaurant::firstOrCreate(
            ['name' => 'La Trattoria'],
            [
                'address'    => 'Calle Mayor 12, Madrid',
                'phone'      => '+34 910 123 456',
                'active'     => true,
                'schedule'   => $schedule,
                'created_by' => $superadmin->id,
            ]
        );

        if ($trattoria->catalogs()->count() === 0) {
            // Catálogo: Carta Principal
            $cartaPrincipal = $trattoria->catalogs()->create([
                'name' => 'Carta Principal', 'description' => 'Nuestra carta del día', 'active' => true, 'order' => 1,
            ]);

            $entradaSection = $cartaPrincipal->sections()->create(['name' => 'Entrantes', 'order' => 1, 'active' => true]);
            $this->createProducts($entradaSection, $trattoria->id, [
                ['Bruschetta al Pomodoro',     'Pan tostado con tomate fresco, ajo y albahaca',              6.50],
                ['Antipasto Misto',            'Selección de embutidos italianos y quesos curados',          12.00],
                ['Carpaccio di Manzo',         'Láminas de ternera con rúcula y parmesano',                  10.50],
                ['Insalata Caprese',           'Mozzarella fresca, tomate y albahaca con aceite de oliva',   8.00],
            ]);

            $pastaSection = $cartaPrincipal->sections()->create(['name' => 'Pastas', 'order' => 2, 'active' => true]);
            $this->createProducts($pastaSection, $trattoria->id, [
                ['Spaghetti Carbonara',        'Panceta, huevo, pecorino y pimienta negra',                  13.50],
                ['Penne all\'Arrabbiata',      'Salsa de tomate picante con ajo y guindilla',                11.00],
                ['Tagliatelle al Ragù',        'Ragù de ternera y cerdo con pasta fresca',                   14.00],
                ['Lasagna della Casa',         'Lasaña casera con bechamel y ragù',                          13.00],
                ['Risotto ai Funghi Porcini',  'Arroz cremoso con setas porcini y parmesano',                14.50],
            ]);

            $pizzaSection = $cartaPrincipal->sections()->create(['name' => 'Pizzas', 'order' => 3, 'active' => true]);
            $this->createProducts($pizzaSection, $trattoria->id, [
                ['Margherita',                 'Tomate, mozzarella fior di latte y albahaca',                11.00],
                ['Diavola',                    'Tomate, mozzarella y salame piccante',                       13.00],
                ['Quattro Stagioni',           'Jamón, champiñones, alcachofas y aceitunas',                 14.00],
                ['Tartufo e Funghi',           'Crema de trufa, mozzarella y setas mixtas',                  16.50],
            ]);

            $secondiSection = $cartaPrincipal->sections()->create(['name' => 'Segundos', 'order' => 4, 'active' => true]);
            $this->createProducts($secondiSection, $trattoria->id, [
                ['Pollo alla Parmigiana',      'Pechuga empanada con salsa de tomate y mozzarella',          15.00],
                ['Saltimbocca alla Romana',    'Ternera con jamón serrano y salvia en salsa de vino blanco', 18.00],
                ['Branzino al Forno',          'Lubina al horno con limón y hierbas',                        19.50],
            ]);

            $postreSection = $cartaPrincipal->sections()->create(['name' => 'Postres', 'order' => 5, 'active' => true]);
            $this->createProducts($postreSection, $trattoria->id, [
                ['Tiramisù della Casa',        'Tiramisú artesanal con mascarpone y café',                   6.50],
                ['Panna Cotta',                'Panna cotta con coulis de frutos rojos',                     5.50],
                ['Gelato Artigianale',         '3 bolas de helado artesanal a elección',                     4.50],
            ]);

            // Catálogo: Bebidas
            $bebidas = $trattoria->catalogs()->create([
                'name' => 'Bebidas', 'description' => 'Vinos, cervezas y más', 'active' => true, 'order' => 2,
            ]);

            $vinosSection = $bebidas->sections()->create(['name' => 'Vinos', 'order' => 1, 'active' => true]);
            $this->createProducts($vinosSection, $trattoria->id, [
                ['Chianti Classico (copa)',    'Toscana, Italia',                                             5.00],
                ['Pinot Grigio (copa)',        'Veneto, Italia',                                              4.50],
                ['Prosecco (copa)',            'Treviso DOC',                                                 4.00],
            ]);

            $noAlcSection = $bebidas->sections()->create(['name' => 'Sin alcohol', 'order' => 2, 'active' => true]);
            $this->createProducts($noAlcSection, $trattoria->id, [
                ['Agua mineral (50cl)',        '',                                                             2.00],
                ['Limonata italiana',          'Limón exprimido con agua con gas',                            3.50],
                ['Café espresso',              '',                                                             1.80],
                ['Cappuccino',                 '',                                                             2.50],
            ]);
        }

        // ══════════════════════════════════════════════════════════════════
        // RESTAURANTE 2 — El Rincón Andaluz
        // ══════════════════════════════════════════════════════════════════
        $rincon = Restaurant::firstOrCreate(
            ['name' => 'El Rincón Andaluz'],
            [
                'address'    => 'Av. de la Constitución 45, Sevilla',
                'phone'      => '+34 954 789 012',
                'active'     => true,
                'schedule'   => array_merge($schedule, ['sunday' => ['enabled' => true, 'open' => '12:00', 'close' => '17:00']]),
                'created_by' => $admin->id,
            ]
        );

        if ($rincon->catalogs()->count() === 0) {
            $tapas = $rincon->catalogs()->create([
                'name' => 'Tapas y Raciones', 'description' => 'Cocina andaluza de siempre', 'active' => true, 'order' => 1,
            ]);

            $frios = $tapas->sections()->create(['name' => 'Fríos', 'order' => 1, 'active' => true]);
            $this->createProducts($frios, $rincon->id, [
                ['Jamón ibérico de bellota',   'Cortado a mano, 80g',                                        14.00],
                ['Salmorejo cordobés',         'Con huevo duro y jamón serrano',                              5.50],
                ['Gazpacho andaluz',           'Vaso de gazpacho tradicional',                                4.00],
                ['Ensaladilla rusa',           'Con atún, aceitunas y pimiento',                              5.00],
            ]);

            $calientes = $tapas->sections()->create(['name' => 'Calientes', 'order' => 2, 'active' => true]);
            $this->createProducts($calientes, $rincon->id, [
                ['Gambas al ajillo',           'Con aceite de oliva virgen extra y guindilla',                9.50],
                ['Croquetas de jamón',         '6 unidades, croquetas caseras',                              7.00],
                ['Pimientos de padrón',        'Fritos con sal en escamas',                                   5.50],
                ['Carrillada ibérica',         'Carrillada guisada con vino de Jerez',                       13.00],
                ['Tortilla española',          'Jugosa, con o sin cebolla',                                   6.50],
                ['Boquerones en tempura',      'Con alioli de limón',                                         8.00],
            ]);

            $platos = $rincon->catalogs()->create([
                'name' => 'Platos Principales', 'description' => 'Para compartir o disfrutar solo', 'active' => true, 'order' => 2,
            ]);

            $arroces = $platos->sections()->create(['name' => 'Arroces', 'order' => 1, 'active' => true]);
            $this->createProducts($arroces, $rincon->id, [
                ['Paella marinera',            'Arroz con mariscos frescos (mín. 2 personas)',                16.50],
                ['Arroz meloso de bogavante',  'Para 2 personas',                                            42.00],
                ['Arroz negro con sepia',      'Con alioli casero',                                           15.00],
            ]);

            $pescados = $platos->sections()->create(['name' => 'Pescados', 'order' => 2, 'active' => true]);
            $this->createProducts($pescados, $rincon->id, [
                ['Urta a la roteña',           'Urta con tomate, pimiento y vino',                            22.00],
                ['Bacalao al pil-pil',         'Bacalao confitado con salsa pil-pil',                         18.00],
                ['Fritura andaluza',           'Surtido de pescaíto frito de la bahía',                       17.00],
            ]);

            $postresRincon = $platos->sections()->create(['name' => 'Postres', 'order' => 3, 'active' => true]);
            $this->createProducts($postresRincon, $rincon->id, [
                ['Torrija caramelizada',       'Con helado de vainilla y miel de caña',                       5.50],
                ['Bienmesabe',                 'Crema de almendra con helado',                                5.00],
                ['Tabla de quesos',            'Quesos andaluces seleccionados con membrillo',                9.00],
            ]);
        }

        // ══════════════════════════════════════════════════════════════════
        // RESTAURANTE 3 — Tokyo Sushi Bar (del admin)
        // ══════════════════════════════════════════════════════════════════
        $tokyo = Restaurant::firstOrCreate(
            ['name' => 'Tokyo Sushi Bar'],
            [
                'address'    => 'Carrer de Provença 88, Barcelona',
                'phone'      => '+34 932 456 789',
                'active'     => true,
                'schedule'   => array_merge($schedule, [
                    'monday' => ['enabled' => false, 'open' => '13:00', 'close' => '23:00'],
                ]),
                'created_by' => $admin->id,
            ]
        );

        if ($tokyo->catalogs()->count() === 0) {
            $sushi = $tokyo->catalogs()->create([
                'name' => 'Menú Sushi', 'description' => 'Pescado fresco del día', 'active' => true, 'order' => 1,
            ]);

            $nigiris = $sushi->sections()->create(['name' => 'Nigiris', 'order' => 1, 'active' => true]);
            $this->createProducts($nigiris, $tokyo->id, [
                ['Nigiri Salmón (2 uds)',      'Salmón fresco sobre arroz de sushi',                          4.50],
                ['Nigiri Atún (2 uds)',        'Atún rojo sobre arroz de sushi',                              5.00],
                ['Nigiri Langostino (2 uds)',  'Langostino cocido sobre arroz',                               4.00],
                ['Nigiri Anguila (2 uds)',     'Anguila glaseada con salsa teriyaki',                         5.50],
            ]);

            $rolls = $sushi->sections()->create(['name' => 'Rolls Especiales', 'order' => 2, 'active' => true]);
            $this->createProducts($rolls, $tokyo->id, [
                ['Dragon Roll (8 uds)',        'Gambas tempura, aguacate y mayonesa picante',                 12.50],
                ['Rainbow Roll (8 uds)',       'California roll cubierto con sashimis variados',              14.00],
                ['Spicy Tuna Roll (8 uds)',    'Atún picante con pepino y sésamo',                            11.00],
                ['Veggie Roll (8 uds)',        'Pepino, aguacate, zanahoria y queso crema',                   9.50],
            ]);

            $sashimis = $sushi->sections()->create(['name' => 'Sashimis', 'order' => 3, 'active' => true]);
            $this->createProducts($sashimis, $tokyo->id, [
                ['Sashimi Salmón (5 uds)',     'Láminas de salmón noruego fresco',                            9.50],
                ['Sashimi Atún (5 uds)',       'Láminas de atún rojo',                                       11.00],
                ['Sashimi Mixto (10 uds)',     'Selección de salmón, atún y pez blanco',                     16.00],
            ]);

            $calentesTokyo = $sushi->sections()->create(['name' => 'Platos Calientes', 'order' => 4, 'active' => true]);
            $this->createProducts($calentesTokyo, $tokyo->id, [
                ['Ramen de miso',              'Caldo de miso con cerdo, huevo y alga nori',                  13.00],
                ['Gyozas de cerdo (6 uds)',    'Empanadillas japonesas a la plancha',                         8.50],
                ['Edamame',                    'Vainas de soja al vapor con sal',                              4.00],
                ['Tempura de verduras',        'Selección de verduras en tempura con salsa ponzu',             9.00],
            ]);

            $bebTokyo = $tokyo->catalogs()->create([
                'name' => 'Bebidas', 'description' => '', 'active' => true, 'order' => 2,
            ]);

            $sakeBev = $bebTokyo->sections()->create(['name' => 'Sake y Cerveza', 'order' => 1, 'active' => true]);
            $this->createProducts($sakeBev, $tokyo->id, [
                ['Sake caliente (150ml)',      'Sake tradicional japonés',                                     5.50],
                ['Sapporo (botella 33cl)',     'Cerveza japonesa',                                             4.00],
                ['Té verde (tetera)',          'Sencha japonés',                                               3.50],
            ]);
        }

        $this->command->info('✅ Datos de demo creados:');
        $this->command->info('   superadmin@scan2order.test / superadmin1234!');
        $this->command->info('   admin@scan2order.test     / admin1234567!');
        $this->command->info('   3 restaurantes con catálogos y productos');
    }

    private function createProducts(Section $section, int $restaurantId, array $items): void
    {
        foreach ($items as $i => [$name, $description, $price]) {
            $section->products()->firstOrCreate(
                ['name' => $name, 'section_id' => $section->id],
                [
                    'description'   => $description ?: null,
                    'price'         => $price,
                    'active'        => true,
                    'restaurant_id' => $restaurantId,
                    'is_new'        => false,
                ]
            );
        }
    }
}
