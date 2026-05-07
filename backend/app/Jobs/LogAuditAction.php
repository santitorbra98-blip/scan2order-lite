<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAuditAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private readonly ?int $actorUserId,
        private readonly ?int $targetUserId,
        private readonly string $action,
        private readonly ?string $resourceType,
        private readonly ?string $resourceId,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly array $metadata,
    ) {}

    public function handle(): void
    {
        AuditLog::create([
            'actor_user_id'  => $this->actorUserId,
            'target_user_id' => $this->targetUserId,
            'action'         => $this->action,
            'resource_type'  => $this->resourceType,
            'resource_id'    => $this->resourceId,
            'ip_address'     => $this->ipAddress,
            'user_agent'     => $this->userAgent,
            'metadata'       => $this->metadata,
            'created_at'     => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // Audit failures are non-critical — logged at warning level only.
        \Illuminate\Support\Facades\Log::warning('LogAuditAction job failed.', [
            'action'    => $this->action,
            'actor'     => $this->actorUserId,
            'exception' => $e->getMessage(),
        ]);
    }
}
