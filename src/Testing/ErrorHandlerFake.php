<?php

namespace HDRUK\ErrorHandler\Testing;

use HDRUK\ErrorHandler\Support\ChannelRegistry;
use HDRUK\ErrorHandler\Support\ErrorReport;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Returned by ErrorHandler::fake(). Wraps the (now faked) channel
 * registry with PHPUnit assertions over the reports recorded by each
 * named channel's FakeChannel, so consuming-app test suites never hit
 * real Slack/GCP.
 */
class ErrorHandlerFake
{
    public function __construct(protected ChannelRegistry $channels) {}

    public function reportsFor(string $channelName): array
    {
        $channel = $this->channels->get($channelName);

        return $channel instanceof FakeChannel ? $channel->reports() : [];
    }

    /**
     * @return array<int, ErrorReport>
     */
    public function allReports(): array
    {
        return array_merge(...array_values(array_map(
            fn (string $name) => $this->reportsFor($name),
            $this->channels->knownChannelNames(),
        ))) ?: [];
    }

    public function assertReported(string $exceptionClass, ?string $channelName = null): void
    {
        $reports = $channelName ? $this->reportsFor($channelName) : $this->allReports();

        PHPUnit::assertTrue(
            collect($reports)->contains(fn ($report) => $report->exceptionClass === $exceptionClass),
            "Failed asserting that [{$exceptionClass}] was reported".($channelName ? " to channel [{$channelName}]." : '.'),
        );
    }

    public function assertReportedToChannel(string $channelName, string $exceptionClass): void
    {
        $this->assertReported($exceptionClass, $channelName);
    }

    public function assertNotReported(string $exceptionClass, ?string $channelName = null): void
    {
        $reports = $channelName ? $this->reportsFor($channelName) : $this->allReports();

        PHPUnit::assertFalse(
            collect($reports)->contains(fn ($report) => $report->exceptionClass === $exceptionClass),
            "Failed asserting that [{$exceptionClass}] was not reported".($channelName ? " to channel [{$channelName}]." : '.'),
        );
    }

    public function assertNothingReported(): void
    {
        PHPUnit::assertEmpty($this->allReports(), 'Failed asserting that nothing was reported.');
    }
}
