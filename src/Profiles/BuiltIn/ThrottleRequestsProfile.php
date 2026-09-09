<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Throwable;

class ThrottleRequestsProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-RATE-LIMIT-001';
    }

    public function httpStatus(Throwable $e): int
    {
        return 429;
    }

    public function publicMessageTemplate(): string
    {
        return 'Too many requests. Please slow down and try again shortly.';
    }

    public function channels(): ?array
    {
        return ['gcp'];
    }
}
