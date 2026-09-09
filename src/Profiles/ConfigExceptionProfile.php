<?php

namespace HDRUK\ErrorHandler\Profiles;

use Throwable;

/**
 * Wraps a plain config array (see config/error-handler.php `exceptions`)
 * as an ExceptionProfile, for cases simple enough not to need a class.
 */
class ConfigExceptionProfile extends BaseExceptionProfile
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(protected array $definition) {}

    public function code(): string
    {
        return $this->definition['code'] ?? 'ERR-UNKNOWN-000';
    }

    public function httpStatus(Throwable $e): int
    {
        return $this->definition['status'] ?? parent::httpStatus($e);
    }

    public function publicMessageTemplate(): string
    {
        return $this->definition['message'] ?? 'Something went wrong.';
    }

    public function publicContext(Throwable $e): array
    {
        $context = $this->definition['public_context'] ?? [];

        if (is_callable($context)) {
            return $context($e);
        }

        return $context;
    }

    public function internalContext(Throwable $e): array
    {
        $context = $this->definition['internal_context'] ?? [];

        if (is_callable($context)) {
            return $context($e);
        }

        return $context;
    }

    public function channels(): ?array
    {
        return $this->definition['channels'] ?? null;
    }

    public function exposePublicContext(): bool
    {
        return (bool) ($this->definition['expose_public_context'] ?? false);
    }
}
