<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Prune audit logs older than the configured retention period (default 90 days)
        $retentionDays = (int) config('security.audit_retention_days', 90);
        $schedule->call(function () use ($retentionDays) {
            \App\Models\AuditLog::where('created_at', '<', now()->subDays($retentionDays))->delete();
        })->dailyAt('02:00')->name('audit-logs.prune')->withoutOverlapping();

        // Delete expired MFA codes every hour
        $schedule->call(function () {
            \App\Models\EmailMfaCode::where('expires_at', '<', now())->delete();
        })->hourly()->name('mfa-codes.prune')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
