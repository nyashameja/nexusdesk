<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Services\Auth\AuthContext;

/**
 * Records privileged/customer-visible actions to the audit trail. Actor, IP
 * and user-agent default to the current request context.
 */
final class AuditService
{
    public function __construct(private readonly AuditRepositoryInterface $repository)
    {
    }

    /**
     * @param array<string,mixed>|null $old
     * @param array<string,mixed>|null $new
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $actorId = null,
    ): void {
        $this->repository->record([
            'user_id'     => $actorId ?? AuthContext::id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }
}
