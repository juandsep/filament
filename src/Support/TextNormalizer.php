<?php

namespace Vibefilter\Filament\Support;

use Normalizer;

/**
 * Evens out differences that don't change meaning, so equal texts share one
 * cache entry. Case, punctuation and emoji stay: "ANGRY!!!" means more than "angry".
 */
class TextNormalizer
{
    public static function text(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = preg_replace('/ *\n */u', "\n", $text);
        $text = preg_replace('/\n{2,}/u', "\n", $text);

        return trim($text);
    }

    /**
     * Questions are also lowercased: "The customer is angry" and
     * "the customer is angry" ask the same thing.
     */
    public static function question(string $question): string
    {
        return mb_strtolower(static::text($question));
    }

    public static function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }
}
