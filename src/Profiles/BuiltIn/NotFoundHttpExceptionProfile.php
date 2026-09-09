<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Throwable;

class NotFoundHttpExceptionProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-NOT-FOUND-002';
    }

    public function httpStatus(Throwable $e): int
    {
        return 404;
    }

    public function publicMessageTemplate(): string
    {
        return 'The requested resource could not be found.';
    }
}
