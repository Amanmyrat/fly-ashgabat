<?php

namespace App\Support;

/**
 * Maps Laravel app locale to ETG B2B language codes.
 */
final class EtgLanguage
{
    /** @var list<string> */
    private const ALLOWED = [
        'en', 'ru', 'de', 'fr', 'es', 'it', 'pt',
        'zh_CN', 'zh_TW', 'ja', 'ko', 'ar', 'tr', 'pl', 'nl', 'uk',
    ];

    /**
     * Language for ETG hotel APIs: current app locale, normalized, else fallback locale, else `en`.
     */
    public static function resolve(): string
    {
        foreach ([app()->getLocale(), config('app.fallback_locale'), 'en'] as $raw) {
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $code = self::normalize($raw);
            if (in_array($code, self::ALLOWED, true)) {
                return $code;
            }
        }

        return 'en';
    }

    private static function normalize(string $locale): string
    {
        $locale = strtolower(str_replace('-', '_', trim($locale)));

        if ($locale === 'zh' || str_starts_with($locale, 'zh_cn')) {
            return 'zh_CN';
        }
        if (str_starts_with($locale, 'zh_tw') || str_starts_with($locale, 'zh_hk')) {
            return 'zh_TW';
        }

        foreach (self::ALLOWED as $code) {
            if (strtolower(str_replace('-', '_', $code)) === $locale) {
                return $code;
            }
        }

        $base = explode('_', $locale)[0] ?? '';

        return in_array($base, self::ALLOWED, true) ? $base : '';
    }
}
