<?php

namespace HDRUK\ErrorHandler\Support;

/**
 * Resolves `{placeholder}` tokens in public message templates. Missing
 * keys never throw — they're left as the literal `{key}` unless a
 * default has been configured.
 */
class TemplateResolver
{
    public function __construct(protected ?string $missingPlaceholderDefault = null) {}

    /**
     * @param  array<string, scalar>  $context
     */
    public function resolve(string $template, array $context): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function (array $matches) use ($context) {
            $key = $matches[1];

            if (array_key_exists($key, $context)) {
                return (string) $context[$key];
            }

            return $this->missingPlaceholderDefault ?? $matches[0];
        }, $template);
    }
}
