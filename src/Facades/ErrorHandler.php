<?php

namespace HDRUK\ErrorHandler\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void mapException(string $exceptionClass, \HDRUK\ErrorHandler\Contracts\ExceptionProfile|class-string $profile)
 * @method static void extend(string $driverName, class-string $channelClass)
 * @method static void route(string $exceptionClass, array $channels)
 * @method static \HDRUK\ErrorHandler\Testing\ErrorHandlerFake fake()
 *
 * @see \HDRUK\ErrorHandler\ErrorHandler
 */
class ErrorHandler extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HDRUK\ErrorHandler\ErrorHandler::class;
    }
}
