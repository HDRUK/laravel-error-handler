<?php

namespace HDRUK\ErrorHandler\Support;

use Illuminate\Support\Str;

class CorrelationIdGenerator
{
    public function __construct(protected string $format = 'ulid') {}

    public function generate(): string
    {
        return $this->format === 'uuid' ? (string) Str::uuid() : (string) Str::ulid();
    }
}
