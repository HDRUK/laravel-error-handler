<?php

namespace HDRUK\ErrorHandler\Support;

use HDRUK\ErrorHandler\Channels\GcpChannel;
use HDRUK\ErrorHandler\Channels\LogChannel;
use HDRUK\ErrorHandler\Channels\SlackChannel;
use HDRUK\ErrorHandler\Contracts\ReportingChannel;
use HDRUK\ErrorHandler\Testing\FakeChannel;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Resolves named channel keys (e.g. "slack-critical") from config into
 * ReportingChannel instances, supporting multiple named instances of the
 * same driver type and consuming-app-registered custom drivers.
 */
class ChannelRegistry
{
    /** @var array<string, class-string<ReportingChannel>> */
    protected array $customDrivers = [];

    /** @var array<string, ReportingChannel> */
    protected array $resolved = [];

    protected bool $faking = false;

    /**
     * @param  array<string, array<string, mixed>>  $config
     */
    public function __construct(protected Application $app, protected array $config = []) {}

    public function extend(string $name, string $channelClass): static
    {
        $this->customDrivers[$name] = $channelClass;

        return $this;
    }

    public function fake(): static
    {
        $this->faking = true;
        $this->resolved = [];

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function knownChannelNames(): array
    {
        return array_unique([...array_keys($this->config), ...array_keys($this->resolved)]);
    }

    public function get(string $name): ReportingChannel
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if ($this->faking) {
            return $this->resolved[$name] = new FakeChannel;
        }

        return $this->resolved[$name] = $this->build($name);
    }

    protected function build(string $name): ReportingChannel
    {
        $definition = $this->config[$name] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("No error-handler channel configured for [{$name}].");
        }

        $driver = $definition['driver'] ?? null;

        return match ($driver) {
            'log' => new LogChannel($this->app->make('log'), $definition['channel'] ?? 'stack'),
            'gcp' => new GcpChannel($definition['stream'] ?? 'php://stderr'),
            'slack' => new SlackChannel($definition['webhook_url'] ?? ''),
            default => $this->buildCustom($driver, $definition),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function buildCustom(?string $driver, array $definition): ReportingChannel
    {
        if ($driver !== null && isset($this->customDrivers[$driver])) {
            return $this->app->make($this->customDrivers[$driver], ['config' => $definition]);
        }

        throw new InvalidArgumentException("Unknown error-handler channel driver [{$driver}].");
    }
}
