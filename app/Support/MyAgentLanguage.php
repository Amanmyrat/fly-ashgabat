<?php

namespace App\Support;

/**
 * Maps Laravel app locale to MyAgent language codes.
 */
final class MyAgentLanguage
{
    public static function resolve(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ru';
    }
}
