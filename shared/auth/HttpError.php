<?php

declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(
        private int $status,
        string $message,
        private string $apiCode = 'error',
        private array $details = []
    ) {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->apiCode;
    }

    public function details(): array
    {
        return $this->details;
    }
}
