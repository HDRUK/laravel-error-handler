<?php

namespace HDRUK\ErrorHandler\Support;

use HDRUK\ErrorHandler\Contracts\ExceptionProfile;
use Throwable;

/**
 * Decides which channel keys a report should be sent to. Precedence,
 * highest to lowest:
 *
 *   1. The exception profile's own channels().
 *   2. An exception-class => channels map in config.
 *   3. Status-code routing: exact code, then "4xx"/"5xx" bucket.
 *   4. The configured default channel.
 *
 * The current environment can override all of the above with a fixed
 * channel list (e.g. silence everything except `log` locally).
 */
class Router
{
    /** @var array<class-string, array<int, string>> */
    protected array $exceptionRouting = [];

    /** @var array<int|string, array<int, string>> */
    protected array $statusRouting = [];

    /** @var array<int, string> */
    protected array $default = ['log'];

    /** @var array<string, array<int, string>|null> */
    protected array $environmentOverrides = [];

    /** @var array<class-string, array<int, string>> */
    protected array $adHocRoutes = [];

    /**
     * @param  array<class-string, array<int, string>>  $exceptionRouting
     * @param  array<int|string, array<int, string>>  $statusRouting
     * @param  array<int, string>  $default
     * @param  array<string, array{channels?: array<int, string>}>  $environments
     */
    public function __construct(
        array $exceptionRouting = [],
        array $statusRouting = [],
        array $default = ['log'],
        array $environments = [],
    ) {
        $this->exceptionRouting = $exceptionRouting;
        $this->statusRouting = $statusRouting;
        $this->default = $default;

        foreach ($environments as $env => $definition) {
            $this->environmentOverrides[$env] = $definition['channels'] ?? null;
        }
    }

    /**
     * Ad-hoc programmatic routing rule, e.g. ErrorHandler::route(Foo::class, ['slack']).
     *
     * @param  array<int, string>  $channels
     */
    public function addRoute(string $exceptionClass, array $channels): static
    {
        $this->adHocRoutes[$exceptionClass] = $channels;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function resolve(Throwable $e, ExceptionProfile $profile, int $httpStatus, ?string $environment = null): array
    {
        if ($environment !== null && array_key_exists($environment, $this->environmentOverrides)) {
            return $this->environmentOverrides[$environment] ?? [];
        }

        foreach ([$e::class, ...class_parents($e) ?: []] as $candidate) {
            if (isset($this->adHocRoutes[$candidate])) {
                return $this->adHocRoutes[$candidate];
            }
        }

        if ($profile->channels() !== null) {
            return $profile->channels();
        }

        foreach ([$e::class, ...class_parents($e) ?: []] as $candidate) {
            if (isset($this->exceptionRouting[$candidate])) {
                return $this->exceptionRouting[$candidate];
            }
        }

        if (isset($this->statusRouting[$httpStatus])) {
            return $this->statusRouting[$httpStatus];
        }

        $bucket = substr((string) $httpStatus, 0, 1).'xx';

        if (isset($this->statusRouting[$bucket])) {
            return $this->statusRouting[$bucket];
        }

        return $this->default;
    }
}
