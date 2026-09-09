<?php

namespace HDRUK\ErrorHandler\Support;

use Illuminate\Http\Request;

/**
 * Builds the request-context slice of a report. Data-minimising by
 * default: no request body, no header values (aside from an allowlist),
 * no query string values (keys only, unless allowlisted). Everything
 * here is opt-in via config, never opt-out.
 */
class RequestContextBuilder
{
    /**
     * @param  array<int, string>  $headerAllowlist
     * @param  array<int, string>  $queryAllowlist
     */
    public function __construct(
        protected array $headerAllowlist = ['X-Request-Id'],
        protected array $queryAllowlist = [],
        protected bool $includeBody = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => optional($request->route())->getName(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'query_keys' => array_keys($request->query()),
            'headers' => $this->allowedHeaders($request),
        ];

        foreach ($this->queryAllowlist as $key) {
            if ($request->query->has($key)) {
                $context['query'][$key] = $request->query($key);
            }
        }

        if ($this->includeBody) {
            $context['body'] = $request->all();
        }

        return $context;
    }

    /**
     * @return array<string, string>
     */
    protected function allowedHeaders(Request $request): array
    {
        $headers = [];

        foreach ($this->headerAllowlist as $header) {
            if ($request->headers->has($header)) {
                $headers[$header] = $request->headers->get($header);
            }
        }

        return $headers;
    }
}
