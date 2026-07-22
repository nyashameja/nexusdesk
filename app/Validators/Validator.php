<?php

declare(strict_types=1);

namespace ParagonHostOps\Validators;

/**
 * Minimal server-side validator. Rules are applied in declaration order and
 * collected per-field. This is deliberately dependency-free.
 */
class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? '';
        if (trim((string) $value) === '') {
            $this->addError($field, "{$label} is required.");
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "{$label} must be a valid email address.");
        }
        return $this;
    }

    public function max(string $field, string $label, int $max): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->addError($field, "{$label} may not exceed {$max} characters.");
        }
        return $this;
    }

    public function min(string $field, string $label, int $min): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        }
        return $this;
    }

    /**
     * @param array<int, string> $allowed
     */
    public function in(string $field, string $label, array $allowed): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->addError($field, "{$label} is not a valid selection.");
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $value);
            if ($d === false || $d->format('Y-m-d') !== $value) {
                $this->addError($field, "{$label} must be a valid date (YYYY-MM-DD).");
            }
        }
        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && !is_numeric($value)) {
            $this->addError($field, "{$label} must be a number.");
        }
        return $this;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }
}
