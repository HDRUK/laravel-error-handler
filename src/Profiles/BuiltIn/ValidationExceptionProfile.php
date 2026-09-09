<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Illuminate\Validation\ValidationException;
use Throwable;

class ValidationExceptionProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-VALIDATION-001';
    }

    public function httpStatus(Throwable $e): int
    {
        return 422;
    }

    public function publicMessageTemplate(): string
    {
        return 'The given data was invalid.';
    }

    public function publicContext(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return ['errors' => $e->errors()];
        }

        return [];
    }

    public function internalContext(Throwable $e): array
    {
        return $this->publicContext($e);
    }

    public function exposePublicContext(): bool
    {
        return true;
    }
}
