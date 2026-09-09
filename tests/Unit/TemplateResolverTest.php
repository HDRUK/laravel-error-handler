<?php

namespace HDRUK\ErrorHandler\Tests\Unit;

use HDRUK\ErrorHandler\Support\TemplateResolver;
use HDRUK\ErrorHandler\Tests\TestCase;

class TemplateResolverTest extends TestCase
{
    public function test_interpolates_known_placeholders(): void
    {
        $resolver = new TemplateResolver;

        $result = $resolver->resolve('The requested {resource} could not be found.', ['resource' => 'record']);

        $this->assertSame('The requested record could not be found.', $result);
    }

    public function test_leaves_missing_placeholders_literal_by_default(): void
    {
        $resolver = new TemplateResolver;

        $result = $resolver->resolve('Ref: {code}-{correlation_id}.', ['code' => 'ERR-1']);

        $this->assertSame('Ref: ERR-1-{correlation_id}.', $result);
    }

    public function test_uses_configured_default_for_missing_placeholders(): void
    {
        $resolver = new TemplateResolver('n/a');

        $result = $resolver->resolve('Value: {missing}.', []);

        $this->assertSame('Value: n/a.', $result);
    }

    public function test_never_throws_on_missing_placeholder(): void
    {
        $resolver = new TemplateResolver;

        $this->expectNotToPerformAssertions();
        $resolver->resolve('{a}{b}{c}', []);
    }
}
