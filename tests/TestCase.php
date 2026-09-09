<?php

namespace HDRUK\ErrorHandler\Tests;

use HDRUK\ErrorHandler\ErrorHandlerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ErrorHandlerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // The shipped default config silences everything in the "testing"
        // environment; tests that want to exercise real routing/dispatch
        // remove that override so status/exception routing applies as it
        // would in production.
        $environments = $app['config']->get('error-handler.environments', []);
        unset($environments['testing']);
        $app['config']->set('error-handler.environments', $environments);
    }
}
