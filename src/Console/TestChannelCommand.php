<?php

namespace HDRUK\ErrorHandler\Console;

use HDRUK\ErrorHandler\Support\ChannelRegistry;
use HDRUK\ErrorHandler\Support\CorrelationIdGenerator;
use HDRUK\ErrorHandler\Support\ErrorReport;
use Illuminate\Console\Command;

class TestChannelCommand extends Command
{
    protected $signature = 'error-handler:test-channel {channel : The configured channel key, e.g. slack-critical}';

    protected $description = 'Send a synthetic test report through a named channel to verify it is wired up correctly.';

    public function handle(ChannelRegistry $channels, CorrelationIdGenerator $ids): int
    {
        $name = $this->argument('channel');

        $report = new ErrorReport(
            code: 'ERR-TEST-000',
            exceptionClass: 'HDRUK\\ErrorHandler\\Console\\TestChannelCommand',
            internalMessage: 'This is a synthetic test report sent via `error-handler:test-channel`.',
            httpStatus: 500,
            severity: 'error',
            correlationId: $ids->generate(),
            timestamp: now()->toIso8601String(),
            environment: app()->environment(),
            internalContext: ['triggered_by' => 'artisan error-handler:test-channel'],
            trace: [],
            requestContext: [],
            channels: [$name],
        );

        try {
            $channels->get($name)->send($report);
        } catch (\Throwable $e) {
            $this->components->error("Failed to send test report to [{$name}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Test report sent to channel [{$name}]. Correlation ID: {$report->correlationId}");

        return self::SUCCESS;
    }
}
