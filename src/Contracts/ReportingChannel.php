<?php

namespace HDRUK\ErrorHandler\Contracts;

use HDRUK\ErrorHandler\Support\ErrorReport;

interface ReportingChannel
{
    public function send(ErrorReport $report): void;
}
