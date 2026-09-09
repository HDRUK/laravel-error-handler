<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Throwable;

class AuthorizationExceptionProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-AUTHZ-001';
    }

    public function httpStatus(Throwable $e): int
    {
        return 403;
    }

    public function publicMessageTemplate(): string
    {
        return 'You are not authorised to perform this action.';
    }
}
