<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Thrown anywhere in the app to produce a JSON error response with a given status code.
 * The front controller catches it and turns it into {"message": ..., "errors": {...}}.
 */
class HttpException extends \RuntimeException
{
    /** @param array<string, string[]> $errors field => list of messages (for 422) */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self(404, $message);
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): self
    {
        return new self(401, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    /** @param array<string, string[]> $errors */
    public static function validation(array $errors, string $message = 'The given data was invalid.'): self
    {
        return new self(422, $message, $errors);
    }
}
