<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMailSettingsFromDb();
        $this->validateLegalConfigInProduction();
    }

    private function loadMailSettingsFromDb(): void
    {
        try {
            $map = [
                'mail_mailer'       => ['mail', 'default'],
                'mail_host'         => ['mail', 'mailers.smtp.host'],
                'mail_port'         => ['mail', 'mailers.smtp.port'],
                'mail_username'     => ['mail', 'mailers.smtp.username'],
                'mail_password'     => ['mail', 'mailers.smtp.password'],
                'mail_encryption'   => ['mail', 'mailers.smtp.encryption'],
                'mail_from_address' => ['mail', 'from.address'],
                'mail_from_name'    => ['mail', 'from.name'],
            ];

            foreach ($map as $key => [$file, $configKey]) {
                $value = Setting::get($key);
                if ($value !== null) {
                    config(["$file.$configKey" => $value]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not load mail settings from DB: ' . $e->getMessage());
        }
    }

    private function validateLegalConfigInProduction(): void
    {
        if (!$this->app->environment('production')) {
            return;
        }

        $placeholders = ['PENDIENTE', 'pendiente', 'tu-dominio.com', '+34 000 000 000', '00000'];
        $critical = ['company_name', 'tax_id', 'address', 'contact_email', 'privacy_email'];
        $unconfigured = [];

        foreach ($critical as $key) {
            $value = (string) config("legal.{$key}");
            foreach ($placeholders as $placeholder) {
                if (stripos($value, $placeholder) !== false) {
                    $unconfigured[] = $key;
                    break;
                }
            }
        }

        if (count($unconfigured) > 0) {
            Log::warning('Legal config contains placeholder values in production.', [
                'fields' => $unconfigured,
            ]);
        }
    }
}
