<?php

namespace HDRUK\ErrorHandler\Channels;

use HDRUK\ErrorHandler\Contracts\ReportingChannel;
use HDRUK\ErrorHandler\Support\ErrorReport;

/**
 * Emits a single structured JSON line per report, shaped for Google
 * Cloud's logging agent (Cloud Run/GKE) to auto-ingest from stdout/stderr
 * — no dependency on the google/cloud-logging SDK. If you're not running
 * on GCP compute and want to ship straight to the Logging API instead,
 * bind your own ReportingChannel using that SDK and register it via
 * ErrorHandler::extend('gcp', YourChannel::class).
 */
class GcpChannel implements ReportingChannel
{
    public function __construct(protected string $stream = 'php://stderr') {}

    public function send(ErrorReport $report): void
    {
        $payload = [
            'severity' => $this->gcpSeverity($report->severity),
            'message' => $report->internalMessage,
            'timestamp' => $report->timestamp,
            'logging.googleapis.com/labels' => [
                'code' => $report->code,
                'correlation_id' => $report->correlationId,
                'environment' => $report->environment,
            ],
            'jsonPayload' => $report->toArray(),
        ];

        $handle = fopen($this->stream, 'a');

        if ($handle === false) {
            return;
        }

        fwrite($handle, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL);
        fclose($handle);
    }

    protected function gcpSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical', 'fatal' => 'CRITICAL',
            'error' => 'ERROR',
            'warning' => 'WARNING',
            default => 'DEFAULT',
        };
    }
}
