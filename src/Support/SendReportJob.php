<?php

namespace HDRUK\ErrorHandler\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued wrapper around a channel's send() call. The channel name is
 * resolved fresh from the container when the job runs, rather than
 * serializing the channel instance itself, since e.g. the Slack/GCP
 * channels only carry plain config anyway and this keeps the dispatch
 * decision (sync vs queued) out of the channel classes entirely.
 */
class SendReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $channelName, public ErrorReport $report) {}

    public function handle(ChannelRegistry $channels): void
    {
        $channels->get($this->channelName)->send($this->report);
    }
}
