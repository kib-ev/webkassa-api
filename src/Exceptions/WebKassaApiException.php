<?php

declare(strict_types=1);

namespace WebKassa\Exceptions;

final class WebKassaApiException extends WebKassaException
{
    public function __construct(
        string $message,
        private readonly string $path,
        private readonly int $statusCode,
        private readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
