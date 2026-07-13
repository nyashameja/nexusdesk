<?php

declare(strict_types=1);

namespace App\Support\Validation;

/**
 * Small rule-based validator. Rules are pipe-delimited strings, e.g.
 *   ['email' => 'required|email', 'subject' => 'required|max:255'].
 * Reject-by-default: only whitelisted, validated fields should be used.
 */
final class Validator
{
    /** @var array<string,string[]> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $messages
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $messages
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function passes(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> Only the validated fields. */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        $isEmpty = $value === null || $value === '';

        switch ($name) {
            case 'required':
                if ($isEmpty) {
                    $this->add($field, $name, "The {$field} field is required.");
                }
                break;
            case 'email':
                if (!$isEmpty && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->add($field, $name, "The {$field} must be a valid email address.");
                }
                break;
            case 'min':
                if (!$isEmpty && mb_strlen((string) $value) < (int) $param) {
                    $this->add($field, $name, "The {$field} must be at least {$param} characters.");
                }
                break;
            case 'max':
                if (!$isEmpty && mb_strlen((string) $value) > (int) $param) {
                    $this->add($field, $name, "The {$field} may not exceed {$param} characters.");
                }
                break;
            case 'numeric':
                if (!$isEmpty && !is_numeric($value)) {
                    $this->add($field, $name, "The {$field} must be a number.");
                }
                break;
            case 'integer':
                if (!$isEmpty && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, $name, "The {$field} must be an integer.");
                }
                break;
            case 'in':
                $options = explode(',', (string) $param);
                if (!$isEmpty && !in_array((string) $value, $options, true)) {
                    $this->add($field, $name, "The selected {$field} is invalid.");
                }
                break;
            case 'confirmed':
                if ($value !== ($this->data[$field . '_confirmation'] ?? null)) {
                    $this->add($field, $name, "The {$field} confirmation does not match.");
                }
                break;
            case 'nullable':
            case '':
                break;
            default:
                // Unknown rule — ignore rather than fail closed on a typo.
                break;
        }
    }

    private function add(string $field, string $rule, string $default): void
    {
        $this->errors[$field][] = $this->messages["{$field}.{$rule}"] ?? $this->messages[$field] ?? $default;
    }
}
