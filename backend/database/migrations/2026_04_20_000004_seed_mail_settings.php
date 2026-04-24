<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            'mail_mailer'       => env('MAIL_MAILER', 'smtp'),
            'mail_host'         => env('MAIL_HOST', 'smtp.gmail.com'),
            'mail_port'         => env('MAIL_PORT', '587'),
            'mail_username'     => env('MAIL_USERNAME', ''),
            'mail_password'     => env('MAIL_PASSWORD', ''),
            'mail_encryption'   => env('MAIL_ENCRYPTION', 'tls'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', ''),
            'mail_from_name'    => env('MAIL_FROM_NAME', 'Scan2order'),
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
        DB::table('settings')->whereIn('key', [
            'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
            'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name',
        ])->delete();
    }
};
