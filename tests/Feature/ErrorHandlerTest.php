<?php

namespace HDRUK\ErrorHandler\Tests\Feature;

use HDRUK\ErrorHandler\ErrorHandler;
use HDRUK\ErrorHandler\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ErrorHandlerTest extends TestCase
{
    public function test_unmapped_exception_never_leaks_its_real_message_to_the_client(): void
    {
        $this->app->instance('request', Request::create('/api/whatever'));

        $handler = $this->app->make(ErrorHandler::class);
        $e = new RuntimeException('leaked internal detail: connection string foo');

        $response = $handler->handleRender($e, Request::create('/api/whatever'));
        $payload = $response->getData(true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('ERR-UNKNOWN-000', $payload['code']);
        $this->assertStringNotContainsString('leaked internal detail', $payload['message']);
        $this->assertArrayHasKey('correlation_id', $payload);
    }

    public function test_render_and_report_share_the_same_correlation_id(): void
    {
        $fake = ErrorHandler::fake();
        $this->app->instance('request', Request::create('/api/whatever'));

        $handler = $this->app->make(ErrorHandler::class);
        $e = new RuntimeException('boom');

        $response = $handler->handleRender($e, Request::create('/api/whatever'));
        $handler->handleReport($e);

        $reports = $fake->reportsFor('gcp');

        $this->assertNotEmpty($reports);
        $this->assertSame($response->getData(true)['correlation_id'], $reports[0]->correlationId);
    }

    public function test_validation_exceptions_expose_field_errors_to_the_client(): void
    {
        $this->app->instance('request', Request::create('/api/whatever'));
        $handler = $this->app->make(ErrorHandler::class);

        $e = ValidationException::withMessages(['email' => ['The email field is required.']]);

        $response = $handler->handleRender($e, Request::create('/api/whatever'));
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('ERR-VALIDATION-001', $payload['code']);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame(['The email field is required.'], $payload['errors']['email']);
    }

    public function test_model_not_found_strips_model_and_id_from_public_response(): void
    {
        $this->app->instance('request', Request::create('/api/whatever'));
        $handler = $this->app->make(ErrorHandler::class);

        $e = (new ModelNotFoundException)->setModel(\stdClass::class, [42]);

        $response = $handler->handleRender($e, Request::create('/api/whatever'));
        $payload = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('stdClass', $payload['message']);
        $this->assertStringNotContainsString('42', $payload['message']);
    }

    public function test_query_exceptions_report_to_slack_critical_and_gcp(): void
    {
        $fake = ErrorHandler::fake();
        $this->app->instance('request', Request::create('/api/whatever'));
        $handler = $this->app->make(ErrorHandler::class);

        $e = new QueryException(
            'mysql', 'select * from users', [], new RuntimeException('SQLSTATE[HY000] Connection refused'),
        );

        $handler->handleReport($e);

        $fake->assertReportedToChannel('slack-critical', get_class($e));
    }

    public function test_map_exception_registers_a_custom_profile(): void
    {
        ErrorHandler::mapException(RuntimeException::class, [
            'code' => 'ERR-CUSTOM-999',
            'status' => 418,
            'message' => "I'm a teapot.",
        ]);

        $this->app->instance('request', Request::create('/api/whatever'));
        $handler = $this->app->make(ErrorHandler::class);

        $response = $handler->handleRender(new RuntimeException('boom'), Request::create('/api/whatever'));
        $payload = $response->getData(true);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('ERR-CUSTOM-999', $payload['code']);
    }

    public function test_route_adds_an_ad_hoc_routing_rule(): void
    {
        $fake = ErrorHandler::fake();
        ErrorHandler::route(RuntimeException::class, ['gcp']);

        $this->app->instance('request', Request::create('/api/whatever'));
        $handler = $this->app->make(ErrorHandler::class);
        $handler->handleReport(new RuntimeException('boom'));

        $fake->assertReportedToChannel('gcp', RuntimeException::class);
    }
}
