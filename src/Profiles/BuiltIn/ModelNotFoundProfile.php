<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * Strips the model class/id from the public-facing message; those
 * details are only ever surfaced in internalContext().
 */
class ModelNotFoundProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-NOT-FOUND-001';
    }

    public function httpStatus(Throwable $e): int
    {
        return 404;
    }

    public function publicMessageTemplate(): string
    {
        return 'The requested {resource} could not be found.';
    }

    public function publicContext(Throwable $e): array
    {
        return ['resource' => 'record'];
    }

    public function internalContext(Throwable $e): array
    {
        if ($e instanceof ModelNotFoundException) {
            return [
                'model' => $e->getModel(),
                'ids' => $e->getIds(),
            ];
        }

        return [];
    }
}
