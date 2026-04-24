<?php

namespace App\Exceptions;

class BusinessException extends \RuntimeException
{
    private int $statusCode;
    private array $data;

    public function __construct(string $message, int $statusCode = 422, array $data = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->data = $data;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function toResponseArray(): array
    {
        return array_merge(['message' => $this->getMessage()], $this->data);
    }
}
