<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Bilingual (Arabic / English) translator.
 *
 * The platform is bilingual-native: RTL/LTR is a first-class concern, not a
 * bolt-on. Language files live in resources/lang/{locale}/{group}.php and
 * return flat key => string maps. Keys use dot notation ("auth.login").
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(
        private string $locale,
        private readonly string $fallback,
        private readonly string $langPath,
    ) {
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function isRtl(): bool
    {
        return in_array($this->locale, ['ar', 'he', 'fa', 'ur'], true);
    }

    public function direction(): string
    {
        return $this->isRtl() ? 'rtl' : 'ltr';
    }

    public function get(string $key, array $replace = []): string
    {
        $line = $this->lookup($key, $this->locale)
            ?? $this->lookup($key, $this->fallback)
            ?? $key;

        foreach ($replace as $search => $value) {
            $line = str_replace([':' . $search, ':' . strtoupper($search)], [(string) $value, strtoupper((string) $value)], $line);
        }

        return $line;
    }

    private function lookup(string $key, string $locale): ?string
    {
        $segments = explode('.', $key);
        $group = array_shift($segments);

        $translations = $this->loadGroup($locale, $group);
        if ($translations === null) {
            return null;
        }

        $value = $translations;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }

        return is_string($value) ? $value : null;
    }

    private function loadGroup(string $locale, string $group): ?array
    {
        $cacheKey = $locale . '.' . $group;
        if (array_key_exists($cacheKey, $this->loaded)) {
            return $this->loaded[$cacheKey];
        }

        $file = $this->langPath . '/' . $locale . '/' . $group . '.php';
        $this->loaded[$cacheKey] = is_file($file) ? require $file : null;

        return $this->loaded[$cacheKey];
    }
}
