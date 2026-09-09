<?php

namespace HDRUK\ErrorHandler\Channels;

use HDRUK\ErrorHandler\Contracts\ReportingChannel;
use HDRUK\ErrorHandler\Support\ErrorReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Posts a Block Kit message to an incoming webhook. Named instances
 * (slack-critical, slack-warnings, ...) each get their own webhook URL
 * via config so routing can target severities at different channels.
 */
class SlackChannel implements ReportingChannel
{
    public function __construct(protected string $webhookUrl) {}

    public function send(ErrorReport $report): void
    {
        if ($this->webhookUrl === '') {
            return;
        }

        Http::post($this->webhookUrl, [
            'blocks' => $this->buildBlocks($report),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildBlocks(ErrorReport $report): array
    {
        $summary = sprintf(
            "*%s* `%s` (HTTP %d) — _%s_\n%s",
            $report->severity,
            $report->code,
            $report->httpStatus,
            $report->environment,
            $report->exceptionClass,
        );

        $blocks = [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $summary],
            ],
            [
                'type' => 'section',
                'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Message:*\n{$report->internalMessage}"],
                    ['type' => 'mrkdwn', 'text' => "*Correlation ID:*\n{$report->correlationId}"],
                    ['type' => 'mrkdwn', 'text' => "*Time:*\n{$report->timestamp}"],
                    ['type' => 'mrkdwn', 'text' => "*Path:*\n".($report->requestContext['path'] ?? 'n/a')],
                ],
            ],
        ];

        if (! empty($report->internalContext)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Context:*\n```".Str::limit(json_encode($report->internalContext, JSON_PRETTY_PRINT), 2500).'```',
                ],
            ];
        }

        if (! empty($report->trace)) {
            $trace = collect($report->trace)
                ->map(fn ($frame) => sprintf('%s:%d %s%s()', $frame['file'], $frame['line'], $frame['class'] ? $frame['class'].'::' : '', $frame['function']))
                ->implode("\n");

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Trace:*\n```".Str::limit($trace, 2500).'```',
                ],
            ];
        }

        return $blocks;
    }
}
