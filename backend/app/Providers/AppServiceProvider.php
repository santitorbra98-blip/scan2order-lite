<?php

namespace App\Providers;

use App\Models\Restaurant;
use App\Policies\RestaurantPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Mail::extend('brevo', function (array $config) {
            return (new BrevoTransportFactory())->create(
                new Dsn('brevo+api', 'default', $config['key'] ?? '')
            );
        });

        Gate::policy(Restaurant::class, RestaurantPolicy::class);

        $this->validateLegalConfigInProduction();
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
