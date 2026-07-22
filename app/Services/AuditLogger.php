<?php

declare(strict_types=1);

namespace ParagonHostOps\Services;

use ParagonHostOps\Core\Request;
use ParagonHostOps\Repositories\AuditLogRepository;

/**
 * Convenience facade over the audit-log repository that automatically captures
 * the acting user, IP and user agent. Never receives secret values.
 */
final class AuditLogger
{
    public function __construct(
        private AuditLogRepository $repository,
        private Auth $auth,
        private Request $request,
    ) {
    }

    public function record(
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
    ): void {
        $this->repository->log(
            $this->auth->id(),
            $action,
            $entityType,
            $entityId,
            $description,
            $this->request->ip(),
            $this->request->userAgent(),
        );
    }
}
