<?php

namespace HDRUK\ErrorHandler\Contracts;

use Throwable;

interface ExceptionProfile
{
    /**
     * Internal stable code, e.g. "ERR-DB-001". Never changes once shipped.
     */
    public function code(): string;

    /**
     * HTTP status to return to the client.
     */
    public function httpStatus(Throwable $e): int;

    /**
     * Safe, public-facing message. May contain {placeholders}.
     */
    public function publicMessageTemplate(): string;

    /**
     * Values to interpolate into the public message template, and/or
     * (when exposePublicContext() is true) extra fields merged into the
     * client response verbatim — e.g. validation field errors.
     *
     * @return array<string, mixed>
     */
    public function publicContext(Throwable $e): array;

    /**
     * Full detail sent to reporting channels only — never the client.
     *
     * @return array<string, mixed>
     */
    public function internalContext(Throwable $e): array;

    /**
     * Which channel keys this should report to. Null falls back to
     * status-code/class based routing.
     *
     * @return array<int, string>|null
     */
    public function channels(): ?array;

    /**
     * Whether publicContext() is safe to include verbatim in the client
     * response (e.g. validation field errors).
     */
    public function exposePublicContext(): bool;
}
