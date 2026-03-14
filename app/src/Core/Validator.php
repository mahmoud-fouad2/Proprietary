<?php
declare(strict_types=1);

namespace Zaco\Core;

/**
 * Input Validation Class
 * Provides comprehensive validation for all user inputs
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $data = [];

    /** @var array<string,mixed> */
    private array $sanitized = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Create from POST data
     */
    public static function fromPost(): self
    {
        return new self($_POST);
    }

    /**
     * Create from GET data
     */
    public static function fromGet(): self
    {
        return new self($_GET);
    }

    /**
     * Create from custom array
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get a validated and sanitized value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->sanitized[$key] ?? $this->data[$key] ?? $default;
    }

    /**
     * Require a field to be present and non-empty
     */
    public function required(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = "الحقل {$label} مطلوب";
        }

        return $this;
    }

    /**
     * Validate string with min/max length
     */
    public function string(string $field, int $minLength = 0, int $maxLength = 255, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            $this->sanitized[$field] = '';
            return $this;
        }

        if (!is_string($value)) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون نصاً";
            return $this;
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if ($minLength > 0 && $length < $minLength) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون {$minLength} حرف على الأقل";
        } elseif ($maxLength > 0 && $length > $maxLength) {
            $this->errors[$field] = "الحقل {$label} يجب ألا يتجاوز {$maxLength} حرف";
        }

        $this->sanitized[$field] = $value;
        return $this;
    }

    /**
     * Validate email format
     */
    public function email(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            return $this;
        }

        $email = filter_var(trim((string)$value), FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون بريد إلكتروني صحيح";
            return $this;
        }

        $this->sanitized[$field] = mb_strtolower($email);
        return $this;
    }

    /**
     * Validate integer with optional range
     */
    public function integer(string $field, ?int $min = null, ?int $max = null, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            $this->sanitized[$field] = 0;
            return $this;
        }

        $intVal = filter_var($value, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون رقماً صحيحاً";
            return $this;
        }

        if ($min !== null && $intVal < $min) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون أكبر من أو يساوي {$min}";
        } elseif ($max !== null && $intVal > $max) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون أصغر من أو يساوي {$max}";
        }

        $this->sanitized[$field] = $intVal;
        return $this;
    }

    /**
     * Validate decimal/float number
     */
    public function decimal(string $field, ?float $min = null, ?float $max = null, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            $this->sanitized[$field] = 0.0;
            return $this;
        }

        $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($floatVal === false) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون رقماً";
            return $this;
        }

        if ($min !== null && $floatVal < $min) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون أكبر من أو يساوي {$min}";
        } elseif ($max !== null && $floatVal > $max) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون أصغر من أو يساوي {$max}";
        }

        $this->sanitized[$field] = $floatVal;
        return $this;
    }

    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d', string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            $this->sanitized[$field] = '';
            return $this;
        }

        $date = \DateTimeImmutable::createFromFormat($format, trim((string)$value));
        if ($date === false || $date->format($format) !== trim((string)$value)) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون تاريخ صحيح";
            return $this;
        }

        $this->sanitized[$field] = $date->format($format);
        return $this;
    }

    /**
     * Validate value is in a list of allowed values
     */
    public function inList(string $field, array $allowed, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            return $this;
        }

        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = "الحقل {$label} يحتوي على قيمة غير مسموحة";
            return $this;
        }

        $this->sanitized[$field] = $value;
        return $this;
    }

    /**
     * Validate password strength
     */
    public function password(string $field, int $minLength = 8, bool $requireMixed = true, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            return $this;
        }

        if (!is_string($value)) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون نصاً";
            return $this;
        }

        if (mb_strlen($value) < $minLength) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون {$minLength} حرف على الأقل";
            return $this;
        }

        if ($requireMixed) {
            if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/[0-9]/', $value)) {
                $this->errors[$field] = "الحقل {$label} يجب أن يحتوي على أحرف وأرقام";
                return $this;
            }
        }

        $this->sanitized[$field] = $value;
        return $this;
    }

    /**
     * Validate URL format
     */
    public function url(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            return $this;
        }

        $url = filter_var(trim((string)$value), FILTER_VALIDATE_URL);
        if ($url === false) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون رابط صحيح";
            return $this;
        }

        $this->sanitized[$field] = $url;
        return $this;
    }

    /**
     * Validate phone number (basic)
     */
    public function phone(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label ?: $field;

        if ($value === null || $value === '') {
            return $this;
        }

        $cleaned = preg_replace('/[^0-9+]/', '', (string)$value);
        if ($cleaned === null || mb_strlen($cleaned) < 8 || mb_strlen($cleaned) > 20) {
            $this->errors[$field] = "الحقل {$label} يجب أن يكون رقم هاتف صحيح";
            return $this;
        }

        $this->sanitized[$field] = $cleaned;
        return $this;
    }

    /**
     * Custom validation with callback
     */
    public function custom(string $field, callable $validator, string $errorMessage): self
    {
        $value = $this->data[$field] ?? null;

        if (!$validator($value)) {
            $this->errors[$field] = $errorMessage;
        }

        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Get all errors
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function firstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }
        return array_values($this->errors)[0];
    }

    /**
     * Get all sanitized data
     * @return array<string,mixed>
     */
    public function validated(): array
    {
        $result = [];
        foreach (array_keys($this->sanitized) as $key) {
            $result[$key] = $this->sanitized[$key];
        }
        // Include non-sanitized data that wasn't explicitly validated
        foreach ($this->data as $key => $value) {
            if (!array_key_exists($key, $result)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Get only the sanitized fields
     * @return array<string,mixed>
     */
    public function sanitized(): array
    {
        return $this->sanitized;
    }

    /**
     * Add a manual error
     */
    public function addError(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }

    /**
     * Validate pagination parameters
     */
    public function pagination(int $maxPerPage = 100, int $maxPage = 10000): self
    {
        $page = (int)($this->data['page'] ?? 1);
        $perPage = (int)($this->data['per_page'] ?? 25);

        $this->sanitized['page'] = max(1, min($page, $maxPage));
        $this->sanitized['per_page'] = max(1, min($perPage, $maxPerPage));

        return $this;
    }

    /**
     * Validate sort parameters
     */
    public function sorting(array $allowedColumns, string $defaultColumn = 'id', string $defaultDir = 'desc'): self
    {
        $sort = (string)($this->data['sort'] ?? $defaultColumn);
        $dir = mb_strtolower((string)($this->data['dir'] ?? $defaultDir));

        if (!in_array($sort, $allowedColumns, true)) {
            $sort = $defaultColumn;
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        $this->sanitized['sort'] = $sort;
        $this->sanitized['dir'] = $dir;

        return $this;
    }
}
