<?php

namespace HDRUK\ErrorHandler\Support;

use HDRUK\ErrorHandler\Contracts\ExceptionProfile;
use HDRUK\ErrorHandler\Profiles\ConfigExceptionProfile;
use HDRUK\ErrorHandler\Profiles\FallbackExceptionProfile;
use Throwable;

/**
 * Holds the exception-class => profile map and resolves the profile for
 * a given throwable, walking up the class hierarchy when there is no
 * exact match, and falling back to a generic profile when nothing matches.
 */
class ProfileRegistry
{
    /** @var array<class-string, ExceptionProfile|class-string<ExceptionProfile>|array<string, mixed>> */
    protected array $profiles = [];

    protected ExceptionProfile $fallback;

    /** @var array<class-string, ExceptionProfile> */
    protected array $resolvedCache = [];

    public function __construct(?ExceptionProfile $fallback = null)
    {
        $this->fallback = $fallback ?? new FallbackExceptionProfile;
    }

    /**
     * @param  class-string  $exceptionClass
     * @param  ExceptionProfile|class-string<ExceptionProfile>|array<string, mixed>  $profile
     */
    public function map(string $exceptionClass, ExceptionProfile|string|array $profile): static
    {
        $this->profiles[$exceptionClass] = $profile;
        unset($this->resolvedCache[$exceptionClass]);

        return $this;
    }

    public function setFallback(ExceptionProfile $fallback): static
    {
        $this->fallback = $fallback;

        return $this;
    }

    public function fallback(): ExceptionProfile
    {
        return $this->fallback;
    }

    /**
     * @return array<class-string, ExceptionProfile|class-string<ExceptionProfile>|array<string, mixed>>
     */
    public function all(): array
    {
        return $this->profiles;
    }

    public function resolve(Throwable $e): ExceptionProfile
    {
        $class = $e::class;

        if (isset($this->resolvedCache[$class])) {
            return $this->resolvedCache[$class];
        }

        $profile = $this->findForClass($class) ?? $this->fallback;

        return $this->resolvedCache[$class] = $profile;
    }

    /**
     * @return array<class-string, ExceptionProfile>
     */
    public function resolvedProfiles(): array
    {
        $resolved = [];

        foreach ($this->profiles as $class => $profile) {
            $resolved[$class] = $this->instantiate($profile);
        }

        return $resolved;
    }

    protected function findForClass(string $class): ?ExceptionProfile
    {
        // Exact match first, then walk up parent classes/interfaces so a
        // mapping registered against a base class covers its subclasses.
        foreach ([$class, ...class_parents($class) ?: []] as $candidate) {
            if (array_key_exists($candidate, $this->profiles)) {
                return $this->instantiate($this->profiles[$candidate]);
            }
        }

        return null;
    }

    protected function instantiate(ExceptionProfile|string|array $profile): ExceptionProfile
    {
        if ($profile instanceof ExceptionProfile) {
            return $profile;
        }

        if (is_array($profile)) {
            return new ConfigExceptionProfile($profile);
        }

        return new $profile;
    }
}
