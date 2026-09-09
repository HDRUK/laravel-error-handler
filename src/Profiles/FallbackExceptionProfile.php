<?php

namespace HDRUK\ErrorHandler\Profiles;

use Throwable;

class FallbackExceptionProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-UNKNOWN-000';
    }

    public function httpStatus(Throwable $e): int
    {
        return 500;
    }

    public function publicMessageTemplate(): string
    {
        return 'Something went wrong. Please contact support and quote this reference: {code}-{correlation_id}.';
    }
}
