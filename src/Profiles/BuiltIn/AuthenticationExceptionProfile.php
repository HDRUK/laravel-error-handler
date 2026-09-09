<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Throwable;

class AuthenticationExceptionProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-AUTH-001';
    }

    public function httpStatus(Throwable $e): int
    {
        return 401;
    }

    public function publicMessageTemplate(): string
    {
        return 'Authentication is required to access this resource.';
    }
}
