<?php

namespace HDRUK\ErrorHandler\Testing;

use HDRUK\ErrorHandler\Contracts\ReportingChannel;
use HDRUK\ErrorHandler\Support\ErrorReport;

/**
 * Records reports in memory instead of sending them. Installed for every
 * configured channel key by ErrorHandler::fake().
 */
class FakeChannel implements ReportingChannel
{
    /** @var array<int, ErrorReport> */
    protected array $reports = [];

    public function send(ErrorReport $report): void
    {
        $this->reports[] = $report;
    }

    /**
     * @return array<int, ErrorReport>
     */
    public function reports(): array
    {
        return $this->reports;
    }
}
