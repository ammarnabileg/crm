<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Thrown when validation fails. Carries the field => [messages] error bag and
 * the original input so it can be flashed back to the form.
 */
class ValidationException extends RuntimeException
{
    public function __construct(
        public readonly array $errors,
        public readonly array $input = [],
        string $message = 'The given data was invalid.',
    ) {
        parent::__construct($message);
    }

    public function first(): ?string
    {
        foreach ($this->errors as $messages) {
            if (! empty($messages)) {
                return $messages[0];
            }
        }

        return null;
    }
}
