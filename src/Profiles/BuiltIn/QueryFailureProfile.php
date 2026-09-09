<?php

namespace HDRUK\ErrorHandler\Profiles\BuiltIn;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Covers Illuminate\Database\QueryException and raw PDOException — the
 * "database is down / query failed" case. The real SQL and connection
 * detail is only ever put into internalContext(), never publicContext().
 */
class QueryFailureProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-DB-001';
    }

    public function httpStatus(Throwable $e): int
    {
        return 503;
    }

    public function publicMessageTemplate(): string
    {
        return 'The service is temporarily unavailable. Please try again shortly.';
    }

    public function internalContext(Throwable $e): array
    {
        if ($e instanceof QueryException) {
            return [
                'connection' => $e->getConnectionName(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
            ];
        }

        if ($e instanceof PDOException) {
            return [
                'sql_state' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
            ];
        }

        return [];
    }

    public function channels(): ?array
    {
        return ['slack-critical'];
    }
}
