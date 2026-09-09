<?php

namespace HDRUK\ErrorHandler\Support;

use HDRUK\ErrorHandler\Contracts\ExceptionProfile;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Builds the sanitised JSON response returned to the client. Only the
 * profile's publicMessageTemplate()/publicContext() ever reach here —
 * internalContext() and the real exception message never do.
 */
class ApiResponseBuilder
{
    public function __construct(protected TemplateResolver $templates) {}

    public function build(ExceptionProfile $profile, Throwable $e, string $correlationId, int $httpStatus): JsonResponse
    {
        $context = $profile->publicContext($e);

        $message = $this->templates->resolve(
            $profile->publicMessageTemplate(),
            array_merge($context, ['code' => $profile->code(), 'correlation_id' => $correlationId]),
        );

        $payload = [
            'message' => $message,
            'code' => $profile->code(),
            'correlation_id' => $correlationId,
        ];

        if ($profile->exposePublicContext()) {
            $payload = array_merge($payload, $context);
        }

        return new JsonResponse($payload, $httpStatus);
    }
}
