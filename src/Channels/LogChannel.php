<?php

namespace HDRUK\ErrorHandler\Channels;

use HDRUK\ErrorHandler\Contracts\ReportingChannel;
use HDRUK\ErrorHandler\Support\ErrorReport;
use Illuminate\Log\LogManager;

/**
 * Plain passthrough to a standard Laravel log channel. Used as the
 * default/fallback and for local/testing environments.
 */
class LogChannel implements ReportingChannel
{
    public function __construct(protected LogManager $logManager, protected string $channel = 'stack') {}

    public function send(ErrorReport $report): void
    {
        $this->logManager->channel($this->channel)->log(
            $this->psrLevel($report->severity),
            $report->internalMessage,
            $report->toArray(),
        );
    }

    protected function psrLevel(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical', 'fatal' => 'critical',
            'error' => 'error',
            'warning' => 'warning',
            default => 'info',
        };
    }
}
