<?php

namespace HDRUK\ErrorHandler\Tests\Unit;

use HDRUK\ErrorHandler\Profiles\BaseExceptionProfile;
use HDRUK\ErrorHandler\Support\ProfileRegistry;
use HDRUK\ErrorHandler\Tests\TestCase;
use RuntimeException;

class ProfileRegistryTest extends TestCase
{
    public function test_falls_back_for_unmapped_exception(): void
    {
        $registry = new ProfileRegistry;

        $profile = $registry->resolve(new RuntimeException('boom'));

        $this->assertSame('ERR-UNKNOWN-000', $profile->code());
    }

    public function test_exact_class_mapping_wins(): void
    {
        $registry = new ProfileRegistry;
        $registry->map(RuntimeException::class, new class extends BaseExceptionProfile
        {
            public function code(): string
            {
                return 'ERR-CUSTOM-001';
            }

            public function publicMessageTemplate(): string
            {
                return 'Custom.';
            }
        });

        $profile = $registry->resolve(new RuntimeException('boom'));

        $this->assertSame('ERR-CUSTOM-001', $profile->code());
    }

    public function test_parent_class_mapping_is_inherited_by_subclasses(): void
    {
        $registry = new ProfileRegistry;
        $registry->map(RuntimeException::class, new class extends BaseExceptionProfile
        {
            public function code(): string
            {
                return 'ERR-PARENT-001';
            }

            public function publicMessageTemplate(): string
            {
                return 'Parent.';
            }
        });

        $child = new class('boom') extends RuntimeException {};

        $profile = $registry->resolve($child);

        $this->assertSame('ERR-PARENT-001', $profile->code());
    }

    public function test_config_array_mapping_is_wrapped_as_a_profile(): void
    {
        $registry = new ProfileRegistry;
        $registry->map(RuntimeException::class, [
            'code' => 'ERR-ARRAY-001',
            'status' => 503,
            'message' => 'Array-defined.',
        ]);

        $profile = $registry->resolve(new RuntimeException('boom'));

        $this->assertSame('ERR-ARRAY-001', $profile->code());
        $this->assertSame(503, $profile->httpStatus(new RuntimeException));
        $this->assertSame('Array-defined.', $profile->publicMessageTemplate());
    }

    public function test_class_string_profile_mapping_is_instantiated(): void
    {
        $registry = new ProfileRegistry;
        $registry->map(RuntimeException::class, DummyProfile::class);

        $profile = $registry->resolve(new RuntimeException('boom'));

        $this->assertInstanceOf(DummyProfile::class, $profile);
        $this->assertSame('ERR-DUMMY-001', $profile->code());
    }
}

class DummyProfile extends BaseExceptionProfile
{
    public function code(): string
    {
        return 'ERR-DUMMY-001';
    }

    public function publicMessageTemplate(): string
    {
        return 'Dummy.';
    }
}
