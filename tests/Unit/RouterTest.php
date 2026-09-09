<?php

namespace HDRUK\ErrorHandler\Tests\Unit;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use HDRUK\ErrorHandler\Support\Router;
use HDRUK\ErrorHandler\Tests\TestCase;
use RuntimeException;

class RouterTest extends TestCase
{
    public function test_profile_channels_take_precedence_over_everything(): void
    {
        $router = new Router(
            exceptionRouting: [RuntimeException::class => ['gcp']],
            statusRouting: ['5xx' => ['slack-critical']],
            default: ['log'],
        );

        $profile = $this->profileWithChannels(['profile-channel']);

        $channels = $router->resolve(new RuntimeException, $profile, 500);

        $this->assertSame(['profile-channel'], $channels);
    }

    public function test_exception_class_routing_wins_over_status_routing(): void
    {
        $router = new Router(
            exceptionRouting: [RuntimeException::class => ['gcp']],
            statusRouting: ['5xx' => ['slack-critical']],
            default: ['log'],
        );

        $channels = $router->resolve(new RuntimeException, $this->profileWithChannels(null), 500);

        $this->assertSame(['gcp'], $channels);
    }

    public function test_exact_status_code_wins_over_bucket(): void
    {
        $router = new Router(
            statusRouting: [429 => ['gcp'], '4xx' => ['log']],
            default: ['log'],
        );

        $channels = $router->resolve(new RuntimeException, $this->profileWithChannels(null), 429);

        $this->assertSame(['gcp'], $channels);
    }

    public function test_falls_back_to_bucket_then_default(): void
    {
        $router = new Router(
            statusRouting: ['5xx' => ['slack-critical']],
            default: ['log'],
        );

        $this->assertSame(['slack-critical'], $router->resolve(new RuntimeException, $this->profileWithChannels(null), 503));
        $this->assertSame(['log'], $router->resolve(new RuntimeException, $this->profileWithChannels(null), 200));
    }

    public function test_environment_override_wins_over_everything(): void
    {
        $router = new Router(
            exceptionRouting: [RuntimeException::class => ['gcp']],
            statusRouting: ['5xx' => ['slack-critical']],
            default: ['log'],
            environments: ['testing' => ['channels' => []]],
        );

        $channels = $router->resolve(new RuntimeException, $this->profileWithChannels(['profile-channel']), 500, 'testing');

        $this->assertSame([], $channels);
    }

    public function test_ad_hoc_route_wins_over_profile_channels(): void
    {
        $router = new Router(default: ['log']);
        $router->addRoute(RuntimeException::class, ['pagerduty']);

        $channels = $router->resolve(new RuntimeException, $this->profileWithChannels(['profile-channel']), 500);

        $this->assertSame(['pagerduty'], $channels);
    }

    protected function profileWithChannels(?array $channels): BaseExceptionProfile
    {
        return new class($channels) extends BaseExceptionProfile
        {
            public function __construct(protected ?array $channels) {}

            public function code(): string
            {
                return 'ERR-TEST-000';
            }

            public function publicMessageTemplate(): string
            {
                return 'Test.';
            }

            public function channels(): ?array
            {
                return $this->channels;
            }
        };
    }
}
