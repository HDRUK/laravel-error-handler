<?php

namespace HDRUK\ErrorHandler\Profiles;

use HDRUK\ErrorHandler\Contracts\ExceptionProfile;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class BaseExceptionProfile implements ExceptionProfile
{
    public function httpStatus(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return 500;
    }

    public function publicContext(Throwable $e): array
    {
        return [];
    }

    public function internalContext(Throwable $e): array
    {
        return [];
    }

    public function channels(): ?array
    {
        return null;
    }

    public function exposePublicContext(): bool
    {
        return false;
    }
}
