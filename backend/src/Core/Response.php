<?php
declare(strict_types=1);

namespace App\Core;

/** JSON response value object. Controllers return these; index.php sends them. */
final class Response
{
    public function __construct(
        public readonly mixed $data,
        public readonly int $status = 200,
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($data, $status);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        if ($this->status !== 204) {
            echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
