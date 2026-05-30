<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued job to persist analytics events without blocking the HTTP response.
 *
 * Dispatched from RestaurantController::show() and AnalyticsController::trackEvent()
 * so that every public menu visit does not incur a synchronous DB write.
 */
class TrackAnalyticsEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private readonly int $restaurantId,
        private readonly string $eventType,
        private readonly ?string $sessionId,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly ?array $metadata = null,
    ) {}

    public function handle(): void
    {
        AnalyticsEvent::create([
            'restaurant_id' => $this->restaurantId,
            'event_type'    => $this->eventType,
            'session_id'    => $this->sessionId,
            'ip_address'    => $this->ipAddress,
            'user_agent'    => $this->userAgent,
            'metadata'      => $this->metadata,
            'created_at'    => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // Analytics failures are non-critical — swallow silently.
        \Illuminate\Support\Facades\Log::debug('TrackAnalyticsEvent failed.', [
            'restaurant_id' => $this->restaurantId,
            'event_type'    => $this->eventType,
            'exception'     => $e->getMessage(),
        ]);
    }
}
