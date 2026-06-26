<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * Rule-based input validator.
 *
 * Usage: Validator::make($data, ['email' => 'required|email|unique:users,email']).
 * On failure it throws a ValidationException carrying a field => messages bag
 * which the error handler flashes back to the form together with old input.
 */
final class Validator
{
    private array $errors = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $attributes = [],
    ) {
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return new self($data, $rules, $messages, $attributes);
    }

    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return ! $this->fails();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Validate and return the cleaned subset, or throw on failure.
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors, $this->data);
        }

        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    private function run(): void
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleset) {
            $rules = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
            $value = $this->data[$field] ?? null;
            $isNullable = in_array('nullable', $rules, true);

            if ($isNullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $parameters] = $this->parseRule($rule);
                $method = 'validate' . str_replace('_', '', ucwords($name, '_'));

                if (method_exists($this, $method) && ! $this->{$method}($field, $value, $parameters)) {
                    $this->addError($field, $name, $parameters);
                    break; // stop at first failure per field
                }
            }
        }
    }

    private function parseRule(string $rule): array
    {
        if (! str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$name, $paramString] = explode(':', $rule, 2);

        return [$name, explode(',', $paramString)];
    }

    // --- Rules -------------------------------------------------------------

    private function validateRequired(string $field, mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && trim((string) $value) !== '';
    }

    private function validateEmail(string $field, mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin(string $field, mixed $value, array $p): bool
    {
        $min = (float) ($p[0] ?? 0);

        return is_numeric($value) ? (float) $value >= $min : mb_strlen((string) $value) >= $min;
    }

    private function validateMax(string $field, mixed $value, array $p): bool
    {
        $max = (float) ($p[0] ?? 0);

        return is_numeric($value) ? (float) $value <= $max : mb_strlen((string) $value) <= $max;
    }

    private function validateBetween(string $field, mixed $value, array $p): bool
    {
        $length = is_numeric($value) ? (float) $value : mb_strlen((string) $value);

        return $length >= (float) ($p[0] ?? 0) && $length <= (float) ($p[1] ?? 0);
    }

    private function validateNumeric(string $field, mixed $value): bool
    {
        return is_numeric($value);
    }

    private function validateInteger(string $field, mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateBoolean(string $field, mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on'], true);
    }

    private function validateConfirmed(string $field, mixed $value): bool
    {
        return ($this->data[$field . '_confirmation'] ?? null) === $value;
    }

    private function validateSame(string $field, mixed $value, array $p): bool
    {
        return ($this->data[$p[0] ?? ''] ?? null) === $value;
    }

    private function validateDifferent(string $field, mixed $value, array $p): bool
    {
        return ($this->data[$p[0] ?? ''] ?? null) !== $value;
    }

    private function validateIn(string $field, mixed $value, array $p): bool
    {
        return in_array((string) $value, $p, true);
    }

    private function validateNotIn(string $field, mixed $value, array $p): bool
    {
        return ! in_array((string) $value, $p, true);
    }

    private function validateAlpha(string $field, mixed $value): bool
    {
        return preg_match('/^[\pL\pM]+$/u', (string) $value) === 1;
    }

    private function validateAlphaNum(string $field, mixed $value): bool
    {
        return preg_match('/^[\pL\pM\pN]+$/u', (string) $value) === 1;
    }

    private function validateAlphaDash(string $field, mixed $value): bool
    {
        return preg_match('/^[\pL\pM\pN_-]+$/u', (string) $value) === 1;
    }

    private function validateUrl(string $field, mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateString(string $field, mixed $value): bool
    {
        return is_string($value);
    }

    private function validateArray(string $field, mixed $value): bool
    {
        return is_array($value);
    }

    private function validateRegex(string $field, mixed $value, array $p): bool
    {
        $pattern = $p[0] ?? '';

        return $pattern !== '' && preg_match($pattern, (string) $value) === 1;
    }

    private function validateDate(string $field, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $ts = strtotime($value);

        return $ts !== false;
    }

    private function validateUnique(string $field, mixed $value, array $p): bool
    {
        $table = $p[0] ?? '';
        $column = $p[1] ?? $field;
        $ignoreId = $p[2] ?? null;

        if ($table === '') {
            return true;
        }

        $query = app('db')->table($table)->where($column, '=', $value);
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('id', '!=', $ignoreId);
        }

        return ! $query->exists();
    }

    private function validateExists(string $field, mixed $value, array $p): bool
    {
        $table = $p[0] ?? '';
        $column = $p[1] ?? $field;

        if ($table === '') {
            return true;
        }

        return app('db')->table($table)->where($column, '=', $value)->exists();
    }

    // --- Errors ------------------------------------------------------------

    private function addError(string $field, string $rule, array $parameters): void
    {
        $label = $this->attributes[$field] ?? str_replace('_', ' ', $field);
        $custom = $this->messages[$field . '.' . $rule] ?? $this->messages[$field] ?? null;

        $this->errors[$field][] = $custom ?? $this->defaultMessage($label, $rule, $parameters);
    }

    private function defaultMessage(string $label, string $rule, array $parameters): string
    {
        $messages = [
            'required'  => "The {$label} field is required.",
            'email'     => "The {$label} must be a valid email address.",
            'min'       => "The {$label} must be at least {$parameters[0]}.",
            'max'       => "The {$label} may not be greater than {$parameters[0]}.",
            'between'   => "The {$label} must be between {$parameters[0]} and {$parameters[1]}.",
            'numeric'   => "The {$label} must be a number.",
            'integer'   => "The {$label} must be an integer.",
            'boolean'   => "The {$label} field must be true or false.",
            'confirmed' => "The {$label} confirmation does not match.",
            'same'      => "The {$label} must match {$parameters[0]}.",
            'different'  => "The {$label} must be different from {$parameters[0]}.",
            'in'        => "The selected {$label} is invalid.",
            'not_in'    => "The selected {$label} is invalid.",
            'alpha'     => "The {$label} may only contain letters.",
            'alpha_num' => "The {$label} may only contain letters and numbers.",
            'alpha_dash' => "The {$label} may only contain letters, numbers, dashes and underscores.",
            'url'       => "The {$label} must be a valid URL.",
            'string'    => "The {$label} must be a string.",
            'array'     => "The {$label} must be an array.",
            'regex'     => "The {$label} format is invalid.",
            'date'      => "The {$label} must be a valid date.",
            'unique'    => "The {$label} has already been taken.",
            'exists'    => "The selected {$label} does not exist.",
        ];

        return $messages[$rule] ?? "The {$label} field is invalid.";
    }
}
