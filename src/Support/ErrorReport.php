<?php

namespace HDRUK\ErrorHandler\Support;

use JsonSerializable;

/**
 * Immutable value object carrying everything a reporting channel needs
 * to describe an exception. Never sent to the client — client responses
 * are built separately from the sanitised public message/context.
 */
final class ErrorReport implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $internalContext
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<string, mixed>  $requestContext
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public readonly string $code,
        public readonly string $exceptionClass,
        public readonly string $internalMessage,
        public readonly int $httpStatus,
        public readonly string $severity,
        public readonly string $correlationId,
        public readonly string $timestamp,
        public readonly string $environment,
        public readonly array $internalContext,
        public readonly array $trace,
        public readonly array $requestContext,
        public readonly array $channels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'exception_class' => $this->exceptionClass,
            'message' => $this->internalMessage,
            'http_status' => $this->httpStatus,
            'severity' => $this->severity,
            'correlation_id' => $this->correlationId,
            'timestamp' => $this->timestamp,
            'environment' => $this->environment,
            'context' => $this->internalContext,
            'trace' => $this->trace,
            'request' => $this->requestContext,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
