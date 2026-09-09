<?php

namespace HDRUK\ErrorHandler\Support;

/**
 * Sends a report to each resolved channel, honouring the per-channel
 * (or global) sync/queue toggle. This is the only place that decides
 * sync vs queued — channel classes never know which mode they're in.
 */
class ReportDispatcher
{
    /**
     * @param  array<string, array<string, mixed>>  $channelConfig
     */
    public function __construct(
        protected ChannelRegistry $channels,
        protected array $channelConfig = [],
        protected bool $queueByDefault = false,
    ) {}

    /**
     * @param  array<int, string>  $channelNames
     */
    public function dispatch(array $channelNames, ErrorReport $report): void
    {
        foreach (array_unique($channelNames) as $name) {
            if ($this->shouldQueue($name)) {
                SendReportJob::dispatch($name, $report);

                continue;
            }

            $this->channels->get($name)->send($report);
        }
    }

    protected function shouldQueue(string $name): bool
    {
        return (bool) ($this->channelConfig[$name]['queue'] ?? $this->queueByDefault);
    }
}
