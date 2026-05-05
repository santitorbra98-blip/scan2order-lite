<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrects the mail settings seeded in 2026_04_20_000004_seed_mail_settings.php.
 * The previous migration stored Mailpit (local dev) values; this migration
 * overwrites them with the values from the environment so that production /
 * staging containers always use the configured SMTP provider.
 *
 * NOTE: env() in migrations is intentional here — these values must match the
 * runtime environment where the migration is executed (i.e. the same container
 * that reads the .env file at boot). Never run migrations from a context that
 * lacks the mail environment variables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            'mail_mailer'       => env('MAIL_MAILER',       'smtp'),
            'mail_host'         => env('MAIL_HOST',         'smtp.gmail.com'),
            'mail_port'         => env('MAIL_PORT',         '587'),
            'mail_username'     => env('MAIL_USERNAME',     ''),
            'mail_password'     => env('MAIL_PASSWORD',     ''),
            'mail_encryption'   => env('MAIL_ENCRYPTION',   'tls'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', ''),
            'mail_from_name'    => env('MAIL_FROM_NAME',    'Scan2order'),
        ];

        foreach ($rows as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Revert to Mailpit defaults used in local development.
        $rows = [
            'mail_mailer'       => 'smtp',
            'mail_host'         => 'mailpit',
            'mail_port'         => '1025',
            'mail_username'     => '',
            'mail_password'     => '',
            'mail_encryption'   => '',
            'mail_from_address' => 'noreply@scan2order.local',
            'mail_from_name'    => 'Scan2order',
        ];

        foreach ($rows as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
