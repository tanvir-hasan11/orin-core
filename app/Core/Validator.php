<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * Minimal rule-based validator.
 *
 * Rules: required, email, min:n, max:n, numeric, in:a,b,c, same:field
 */
final class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     */
    public function __construct(private array $data, private array $rules)
    {
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;

            foreach (explode('|', $ruleString) as $rule) {
                $this->apply($field, $value, $rule);
            }
        }

        return $this->errors === [];
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
    }

    private function apply(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $label = ucfirst(str_replace('_', ' ', $field));

        $fail = static function (string $message) use ($field): void {
            // handled below via closure binding workaround
        };

        $isBlank = $value === null || $value === '';

        switch ($name) {
            case 'required':
                if ($isBlank) {
                    $this->add($field, $label . ' is required.');
                }
                break;

            case 'email':
                if (!$isBlank && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->add($field, $label . ' must be a valid email address.');
                }
                break;

            case 'numeric':
                if (!$isBlank && !is_numeric($value)) {
                    $this->add($field, $label . ' must be numeric.');
                }
                break;

            case 'min':
                if (!$isBlank && mb_strlen((string) $value) < (int) $param) {
                    $this->add($field, sprintf('%s must be at least %d characters.', $label, (int) $param));
                }
                break;

            case 'max':
                if (!$isBlank && mb_strlen((string) $value) > (int) $param) {
                    $this->add($field, sprintf('%s may not be longer than %d characters.', $label, (int) $param));
                }
                break;

            case 'in':
                $allowed = $param === null ? [] : explode(',', $param);
                if (!$isBlank && !in_array((string) $value, $allowed, true)) {
                    $this->add($field, $label . ' is not a valid choice.');
                }
                break;

            case 'same':
                if ($param !== null && ($this->data[$param] ?? null) !== $value) {
                    $this->add($field, $label . ' must match ' . $param . '.');
                }
                break;
        }
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
