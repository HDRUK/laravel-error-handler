<?php

namespace HDRUK\ErrorHandler;

use HDRUK\ErrorHandler\Console\ListCodesCommand;
use HDRUK\ErrorHandler\Console\TestChannelCommand;
use HDRUK\ErrorHandler\Contracts\ExceptionProfile;
use HDRUK\ErrorHandler\Support\ApiResponseBuilder;
use HDRUK\ErrorHandler\Support\ChannelRegistry;
use HDRUK\ErrorHandler\Support\CorrelationIdGenerator;
use HDRUK\ErrorHandler\Support\CorrelationIdStore;
use HDRUK\ErrorHandler\Support\ProfileRegistry;
use HDRUK\ErrorHandler\Support\ReportDispatcher;
use HDRUK\ErrorHandler\Support\RequestContextBuilder;
use HDRUK\ErrorHandler\Support\Router;
use HDRUK\ErrorHandler\Support\TemplateResolver;
use HDRUK\ErrorHandler\Support\TraceTrimmer;
use Illuminate\Support\ServiceProvider;

class ErrorHandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/error-handler.php', 'error-handler');

        $this->app->singleton(ProfileRegistry::class, function ($app) {
            $config = $app['config']->get('error-handler');

            $fallback = $config['fallback'] ?? null;
            $registry = new ProfileRegistry($fallback ? $app->make($fallback) : null);

            foreach ($config['exceptions'] ?? [] as $exceptionClass => $profile) {
                $registry->map($exceptionClass, $this->resolveProfileDefinition($profile));
            }

            return $registry;
        });

        $this->app->singleton(Router::class, function ($app) {
            $config = $app['config']->get('error-handler');

            return new Router(
                exceptionRouting: $config['exception_routing'] ?? [],
                statusRouting: $config['status_routing'] ?? [],
                default: $config['default_channels'] ?? ['log'],
                environments: $config['environments'] ?? [],
            );
        });

        $this->app->singleton(ChannelRegistry::class, function ($app) {
            return new ChannelRegistry($app, $app['config']->get('error-handler.channels', []));
        });

        $this->app->singleton(ReportDispatcher::class, function ($app) {
            $config = $app['config']->get('error-handler');

            return new ReportDispatcher(
                channels: $app->make(ChannelRegistry::class),
                channelConfig: $config['channels'] ?? [],
                queueByDefault: (bool) ($config['queue']['enabled'] ?? false),
            );
        });

        $this->app->singleton(TraceTrimmer::class, function ($app) {
            $trace = $app['config']->get('error-handler.context.trace', []);

            return new TraceTrimmer(
                depth: $trace['depth'] ?? 10,
                exclude: $trace['exclude'] ?? ['*/vendor/*'],
                allow: $trace['allow'] ?? [],
            );
        });

        $this->app->singleton(RequestContextBuilder::class, function ($app) {
            $request = $app['config']->get('error-handler.context.request', []);

            return new RequestContextBuilder(
                headerAllowlist: $request['header_allowlist'] ?? ['X-Request-Id'],
                queryAllowlist: $request['query_allowlist'] ?? [],
                includeBody: (bool) ($request['include_body'] ?? false),
            );
        });

        $this->app->singleton(CorrelationIdGenerator::class, function ($app) {
            return new CorrelationIdGenerator($app['config']->get('error-handler.correlation_id.format', 'ulid'));
        });

        $this->app->singleton(CorrelationIdStore::class, function ($app) {
            return new CorrelationIdStore($app->make(CorrelationIdGenerator::class));
        });

        $this->app->singleton(TemplateResolver::class, fn () => new TemplateResolver);

        $this->app->singleton(ApiResponseBuilder::class, function ($app) {
            return new ApiResponseBuilder($app->make(TemplateResolver::class));
        });

        $this->app->singleton(ErrorHandler::class, function ($app) {
            return new ErrorHandler(
                app: $app,
                profiles: $app->make(ProfileRegistry::class),
                router: $app->make(Router::class),
                channels: $app->make(ChannelRegistry::class),
                dispatcher: $app->make(ReportDispatcher::class),
                responses: $app->make(ApiResponseBuilder::class),
                correlationIds: $app->make(CorrelationIdStore::class),
                traceTrimmer: $app->make(TraceTrimmer::class),
                requestContext: $app->make(RequestContextBuilder::class),
                environment: $app->environment(),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/error-handler.php' => config_path('error-handler.php'),
            ], 'error-handler-config');

            $this->publishes([
                __DIR__.'/../stubs/ErrorCodes.php.stub' => app_path('Exceptions/ErrorCodes.php'),
            ], 'error-handler-stubs');

            $this->commands([
                ListCodesCommand::class,
                TestChannelCommand::class,
            ]);
        }
    }

    /**
     * @param  class-string<ExceptionProfile>|array<string, mixed>  $profile
     * @return class-string<ExceptionProfile>|array<string, mixed>
     */
    protected function resolveProfileDefinition(string|array $profile): string|array
    {
        if (is_array($profile) && isset($profile['profile'])) {
            return $profile['profile'];
        }

        return $profile;
    }
}
