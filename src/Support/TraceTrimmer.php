<?php

namespace HDRUK\ErrorHandler\Support;

use Throwable;

/**
 * Trims a throwable's stack trace to a manageable, mostly-application-code
 * slice: excludes frames matching a denylist of path patterns (vendor/ by
 * default), optionally restricts to an allowlist, then caps the result to
 * the configured max depth.
 */
class TraceTrimmer
{
    /**
     * @param  array<int, string>  $exclude  fnmatch-style path patterns to drop
     * @param  array<int, string>  $allow  fnmatch-style path patterns to keep exclusively, when non-empty
     */
    public function __construct(
        protected int $depth = 10,
        protected array $exclude = ['*/vendor/*'],
        protected array $allow = [],
    ) {}

    /**
     * @return array<int, array{file: string, line: int, function: string, class: ?string}>
     */
    public function trim(Throwable $e): array
    {
        $frames = [];

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? '';

            if ($this->isExcluded($file)) {
                continue;
            }

            $frames[] = [
                'file' => $file,
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'],
                'class' => $frame['class'] ?? null,
            ];

            if (count($frames) >= $this->depth) {
                break;
            }
        }

        return $frames;
    }

    protected function isExcluded(string $file): bool
    {
        if ($file === '') {
            return false;
        }

        if (! empty($this->allow)) {
            foreach ($this->allow as $pattern) {
                if (fnmatch($pattern, $file)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($this->exclude as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }

        return false;
    }
}
