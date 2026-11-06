<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
