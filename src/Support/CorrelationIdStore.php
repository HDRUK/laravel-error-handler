<?php

namespace HDRUK\ErrorHandler\Support;

use Throwable;
use WeakMap;

/**
 * Laravel invokes the reportable() and render() callbacks separately for
 * the same throwable; this ensures both see the same correlation ID
 * without mutating the exception itself.
 */
class CorrelationIdStore
{
    /** @var WeakMap<Throwable, string> */
    protected WeakMap $ids;

    public function __construct(protected CorrelationIdGenerator $generator)
    {
        $this->ids = new WeakMap;
    }

    public function for(Throwable $e): string
    {
        return $this->ids[$e] ??= $this->generator->generate();
    }
}
