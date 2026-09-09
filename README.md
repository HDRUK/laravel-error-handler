# Laravel Error Handler

A drop-in exception handler for Laravel 12 APIs. It intercepts every
exception, returns a sanitised JSON response to the client, and routes the
real detail (with context) to one or more configurable reporting channels
based on status code and/or exception type.

- Sensitive internals (DB errors, stack traces, internal messages) never
  reach the client — just a safe message, an error code, and a correlation
  ID.
- Routing, channels, message templates, and exception mappings are all
  configurable without editing package code.
- Ships with `log`, `gcp` (structured stdout JSON for Cloud Run/GKE), and
  `slack` (Block Kit webhook) channels, plus a `ReportingChannel` contract
  for your own.

## Installation

```bash
composer require hdruk/laravel-error-handler
```

Publish the config file:

```bash
php artisan vendor:publish --tag=error-handler-config
```

Wire the package into Laravel 12's exception handling. This is the one line
of cooperation the framework requires from the host app — a package can't
silently take over `withExceptions()`:

```php
// bootstrap/app.php
use HDRUK\ErrorHandler\ErrorHandler;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    ErrorHandler::register($exceptions);
})
```

That's it — every exception in the app is now intercepted, sanitised, and
reported.

## How it works

For every exception:

1. It's resolved to an **exception profile** (exact class match → nearest
   mapped parent class → the fallback profile).
2. The profile decides the HTTP status, the public-facing message (with
   `{placeholder}` interpolation), and whether any of its context is safe to
   expose to the client.
3. A **correlation ID** is generated once per exception and included in both
   the client response and every channel report.
4. The client gets:
   ```json
   {
       "message": "The service is temporarily unavailable. Please try again shortly.",
       "code": "ERR-DB-001",
       "correlation_id": "01J..."
   }
   ```
5. Separately, a **router** decides which channel(s) get the full detail
   (exception class, real message, internal context, trimmed stack trace,
   request metadata), in this precedence order:
   1. An ad-hoc rule added via `ErrorHandler::route()`.
   2. The exception profile's own `channels()`.
   3. An exception-class → channels map (`exception_routing` in config).
   4. Status-code routing — an exact code before its `4xx`/`5xx` bucket.
   5. The configured default channel.

   The current environment (`environments` in config) can override all of
   the above with a fixed channel list, e.g. silencing everything except
   `log` locally.

## Defining exception profiles

**Config array** — for simple cases:

```php
// config/error-handler.php
'exceptions' => [
    \App\Exceptions\PaymentGatewayException::class => [
        'code' => 'ERR-PAYMENTS-001',
        'status' => 502,
        'message' => 'We could not reach the payment provider.',
        'channels' => ['slack-critical'],
        // optional: values merged into the client response verbatim.
        // Only do this for genuinely safe fields (see ValidationException below).
        'expose_public_context' => false,
    ],
],
```

**Class-based profile** — for anything needing logic:

```php
use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;

class PaymentGatewayProfile extends BaseExceptionProfile
{
    public function code(): string { return 'ERR-PAYMENTS-001'; }
    public function httpStatus(\Throwable $e): int { return 502; }
    public function publicMessageTemplate(): string
    {
        return 'We could not reach the payment provider.';
    }
    public function internalContext(\Throwable $e): array
    {
        return ['gateway_response' => $e->gatewayPayload()];
    }
}
```

Register it either in config (`'exceptions' => [PaymentGatewayException::class => PaymentGatewayProfile::class]`)
or programmatically, e.g. in a service provider's `boot()`:

```php
use HDRUK\ErrorHandler\Facades\ErrorHandler;

ErrorHandler::mapException(PaymentGatewayException::class, PaymentGatewayProfile::class);
```

A mapping registered against a base class is inherited by its subclasses.

### Shipped profiles

| Exception | Status | Code |
|---|---|---|
| `Illuminate\Database\QueryException` / `PDOException` | 503 | `ERR-DB-001` |
| `Illuminate\Validation\ValidationException` | 422 | `ERR-VALIDATION-001` |
| `Illuminate\Auth\AuthenticationException` | 401 | `ERR-AUTH-001` |
| `Illuminate\Auth\Access\AuthorizationException` | 403 | `ERR-AUTHZ-001` |
| `Illuminate\Database\Eloquent\ModelNotFoundException` | 404 | `ERR-NOT-FOUND-001` |
| `Symfony\...\NotFoundHttpException` | 404 | `ERR-NOT-FOUND-002` |
| `Illuminate\Http\Exceptions\ThrottleRequestsException` | 429 | `ERR-RATE-LIMIT-001` |
| Unmapped `Throwable` | 500 | `ERR-UNKNOWN-000` |

`ValidationException` is the one profile with `expose_public_context()`
returning `true` — its field-level `errors` are genuinely safe (and useful)
to hand back to the client.

Run `php artisan error-handler:list-codes` any time to see the full,
currently-registered code table.

## Channels

Named channel *instances*, not just types — `slack-critical` and
`slack-warnings` are both the `slack` driver with different webhook URLs, so
routing can target them independently:

```php
'channels' => [
    'slack-critical' => ['driver' => 'slack', 'webhook_url' => env('ERROR_HANDLER_SLACK_CRITICAL_WEBHOOK')],
    'slack-warnings' => ['driver' => 'slack', 'webhook_url' => env('ERROR_HANDLER_SLACK_WARNINGS_WEBHOOK')],
    'gcp' => ['driver' => 'gcp'],
    'log' => ['driver' => 'log', 'channel' => 'stack'],
],
```

- **`log`** — plain passthrough to a standard Laravel log channel.
- **`gcp`** — writes one structured JSON line per report straight to
  `php://stderr` (configurable), shaped for Cloud Run/GKE's logging agent to
  auto-ingest. No dependency on `google/cloud-logging`. If you're not on GCP
  compute and want to ship to the Logging API directly instead, write your
  own `ReportingChannel` using that SDK and register it as the `gcp` driver
  via `ErrorHandler::extend('gcp', YourChannel::class)`.
- **`slack`** — posts a Block Kit message to an incoming webhook: summary,
  code, correlation ID, internal context, and a trimmed stack trace.

Register your own driver:

```php
ErrorHandler::extend('pagerduty', PagerDutyChannel::class);
```

```php
'channels' => [
    'pagerduty' => ['driver' => 'pagerduty', 'routing_key' => env('PAGERDUTY_ROUTING_KEY')],
],
```

Custom channel classes are resolved via the container as
`new YourChannel(config: $definition)` — accept a `$config` array (the
channel's config entry, minus the `driver` key) in the constructor.

### Sync vs queued delivery

Channels send synchronously by default, during the request lifecycle —
important for the "the DB is down, tell Slack now" case. Toggle a channel to
queue instead:

```php
'channels' => [
    'slack-warnings' => ['driver' => 'slack', 'webhook_url' => '...', 'queue' => true],
],
```

or flip the global default in `queue.enabled`. This is purely a dispatch
decision — channel classes never know whether they're running sync or
queued.

## Context enrichment

Every report (never the client response) includes: correlation ID,
timestamp, environment, exception class/message, the profile's
`internalContext()`, a trimmed stack trace, and request metadata (method,
path, route name, authenticated user's primary key only).

Data-minimising by default: request body, header values (aside from an
allowlist), and query string values (aside from an allowlist) are all
excluded unless you opt in via `config('error-handler.context.request')`.

Stack traces are trimmed to the top N frames (`context.trace.depth`,
default 10) with `vendor/` frames excluded by default
(`context.trace.exclude`); set `context.trace.allow` to restrict to an
allowlist of path patterns instead.

## Testing

Fake every channel and assert on what would have been reported, without
hitting real Slack/GCP:

```php
use HDRUK\ErrorHandler\Facades\ErrorHandler;

$fake = ErrorHandler::fake();

// ... trigger the exception ...

$fake->assertReported(DatabaseUnavailableException::class);
$fake->assertReportedToChannel('slack-critical', DatabaseUnavailableException::class);
$fake->assertNotReported(SomeOtherException::class);
$fake->assertNothingReported();
```

## Artisan commands

```bash
php artisan error-handler:list-codes
php artisan error-handler:test-channel slack-critical
```

## Configuration reference

See the fully-commented [`config/error-handler.php`](config/error-handler.php)
for every available option: `channels`, `status_routing`,
`exception_routing`, `default_channels`, `environments`, `exceptions`,
`fallback`, `context`, `queue`, and `correlation_id`.
