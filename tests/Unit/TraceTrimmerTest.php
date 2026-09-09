<?php

namespace HDRUK\ErrorHandler\Tests\Unit;

use HDRUK\ErrorHandler\Support\TraceTrimmer;
use HDRUK\ErrorHandler\Tests\TestCase;
use RuntimeException;

class TraceTrimmerTest extends TestCase
{
    public function test_excludes_vendor_frames_by_default(): void
    {
        $trimmer = new class(10, ['*/vendor/*'], []) extends TraceTrimmer
        {
            public function fakeTrim(array $trace): array
            {
                $e = new RuntimeException;
                $ref = (new \ReflectionClass(\Exception::class))->getProperty('trace');
                $ref->setAccessible(true);
                $ref->setValue($e, $trace);

                return $this->trim($e);
            }
        };

        $frames = $trimmer->fakeTrim([
            ['file' => '/app/vendor/some/pkg/File.php', 'line' => 1, 'function' => 'foo'],
            ['file' => '/app/src/Controller.php', 'line' => 10, 'function' => 'bar'],
        ]);

        $this->assertCount(1, $frames);
        $this->assertSame('/app/src/Controller.php', $frames[0]['file']);
    }

    public function test_caps_to_configured_depth(): void
    {
        $trimmer = new class(2, [], []) extends TraceTrimmer
        {
            public function fakeTrim(array $trace): array
            {
                $e = new RuntimeException;
                $ref = (new \ReflectionClass(\Exception::class))->getProperty('trace');
                $ref->setAccessible(true);
                $ref->setValue($e, $trace);

                return $this->trim($e);
            }
        };

        $frames = $trimmer->fakeTrim([
            ['file' => '/a.php', 'line' => 1, 'function' => 'a'],
            ['file' => '/b.php', 'line' => 2, 'function' => 'b'],
            ['file' => '/c.php', 'line' => 3, 'function' => 'c'],
        ]);

        $this->assertCount(2, $frames);
    }
}
