<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Typed ticket entity, hydrated with joined display fields (status/priority/
 * department names and colours, requester/agent names) for list & detail views.
 */
final class Ticket
{
    /** @param array<string,mixed> $attributes */
    public function __construct(private readonly array $attributes)
    {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function id(): int { return (int) $this->attributes['id']; }
    public function reference(): string { return (string) $this->attributes['reference']; }
    public function subject(): string { return (string) $this->attributes['subject']; }
    public function statusSlug(): string { return (string) ($this->attributes['status_slug'] ?? ''); }
    public function statusName(): string { return (string) ($this->attributes['status_name'] ?? ''); }
    public function statusColour(): string { return (string) ($this->attributes['status_colour'] ?? '#6c757d'); }
    public function prioritySlug(): string { return (string) ($this->attributes['priority_slug'] ?? ''); }
    public function priorityName(): string { return (string) ($this->attributes['priority_name'] ?? ''); }
    public function priorityColour(): string { return (string) ($this->attributes['priority_colour'] ?? '#6c757d'); }
    public function departmentName(): string { return (string) ($this->attributes['department_name'] ?? ''); }
    public function requesterName(): string { return (string) ($this->attributes['requester_name'] ?? ($this->attributes['requester_full_name'] ?? 'Guest')); }
    public function requesterEmail(): ?string { return $this->attributes['requester_email'] ?? null; }
    public function assignedAgentName(): ?string { return $this->attributes['agent_name'] ?? null; }
    public function companyName(): ?string { return $this->attributes['company_name'] ?? null; }
    public function isResolved(): bool { return (bool) ($this->attributes['is_resolved'] ?? false); }
    public function dueResolutionAt(): ?string { return $this->attributes['due_resolution_at'] ?? null; }
    public function slaResolutionBreached(): bool { return (bool) ($this->attributes['sla_resolution_breached'] ?? false); }
    public function createdAt(): string { return (string) ($this->attributes['created_at'] ?? ''); }
    public function requesterId(): ?int { $v = $this->attributes['requester_id'] ?? null; return $v !== null ? (int) $v : null; }
    public function companyId(): ?int { $v = $this->attributes['company_id'] ?? null; return $v !== null ? (int) $v : null; }
    public function departmentId(): int { return (int) $this->attributes['department_id']; }
    public function assignedAgentId(): ?int { $v = $this->attributes['assigned_agent_id'] ?? null; return $v !== null ? (int) $v : null; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
