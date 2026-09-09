<?php

namespace HDRUK\ErrorHandler;

use HDRUK\ErrorHandler\Contracts\ExceptionProfile;
use HDRUK\ErrorHandler\Support\ApiResponseBuilder;
use HDRUK\ErrorHandler\Support\ChannelRegistry;
use HDRUK\ErrorHandler\Support\CorrelationIdStore;
use HDRUK\ErrorHandler\Support\ErrorReport;
use HDRUK\ErrorHandler\Support\ProfileRegistry;
use HDRUK\ErrorHandler\Support\ReportDispatcher;
use HDRUK\ErrorHandler\Support\RequestContextBuilder;
use HDRUK\ErrorHandler\Support\Router;
use HDRUK\ErrorHandler\Support\TraceTrimmer;
use HDRUK\ErrorHandler\Testing\ErrorHandlerFake;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Single entry point for both the framework wiring (register()) and the
 * programmatic config API (mapException/extend/route/fake) a consuming
 * app uses at boot time instead of hand-editing the published config.
 */
class ErrorHandler
{
    public function __construct(
        protected Application $app,
        protected ProfileRegistry $profiles,
        protected Router $router,
        protected ChannelRegistry $channels,
        protected ReportDispatcher $dispatcher,
        protected ApiResponseBuilder $responses,
        protected CorrelationIdStore $correlationIds,
        protected TraceTrimmer $traceTrimmer,
        protected RequestContextBuilder $requestContext,
        protected string $environment,
    ) {}

    /**
     * The one line a consuming app adds to bootstrap/app.php:
     *
     *   ->withExceptions(fn (Exceptions $exceptions) => ErrorHandler::register($exceptions))
     */
    public static function register(Exceptions $exceptions): void
    {
        app(self::class)->boot($exceptions);
    }

    public function boot(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request): JsonResponse {
            return $this->handleRender($e, $request);
        });

        $exceptions->reportable(function (Throwable $e): bool {
            $this->handleReport($e);

            // Stop Laravel's default reporting/logging — this package is
            // now the sole owner of where the exception detail goes.
            return false;
        });
    }

    public function handleRender(Throwable $e, Request $request): JsonResponse
    {
        $profile = $this->profiles->resolve($e);
        $correlationId = $this->correlationIds->for($e);
        $status = $profile->httpStatus($e);

        return $this->responses->build($profile, $e, $correlationId, $status);
    }

    public function handleReport(Throwable $e): void
    {
        $profile = $this->profiles->resolve($e);
        $status = $profile->httpStatus($e);
        $correlationId = $this->correlationIds->for($e);

        $channelNames = $this->router->resolve($e, $profile, $status, $this->environment);

        if (empty($channelNames)) {
            return;
        }

        $report = new ErrorReport(
            code: $profile->code(),
            exceptionClass: $e::class,
            internalMessage: $e->getMessage(),
            httpStatus: $status,
            severity: $status >= 500 ? 'error' : 'warning',
            correlationId: $correlationId,
            timestamp: now()->toIso8601String(),
            environment: $this->environment,
            internalContext: $profile->internalContext($e),
            trace: $this->traceTrimmer->trim($e),
            requestContext: $this->requestContextFor($e),
            channels: $channelNames,
        );

        $this->dispatcher->dispatch($channelNames, $report);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestContextFor(Throwable $e): array
    {
        if (! $this->app->bound('request')) {
            return [];
        }

        return $this->requestContext->build($this->app->make('request'));
    }

    /**
     * @param  ExceptionProfile|class-string<ExceptionProfile>|array<string, mixed>  $profile
     */
    public static function mapException(string $exceptionClass, ExceptionProfile|string|array $profile): void
    {
        app(self::class)->profiles->map($exceptionClass, $profile);
    }

    public static function extend(string $driverName, string $channelClass): void
    {
        app(self::class)->channels->extend($driverName, $channelClass);
    }

    /**
     * @param  array<int, string>  $channels
     */
    public static function route(string $exceptionClass, array $channels): void
    {
        app(self::class)->router->addRoute($exceptionClass, $channels);
    }

    public static function fake(): ErrorHandlerFake
    {
        return new ErrorHandlerFake(app(self::class)->channels->fake());
    }
}
