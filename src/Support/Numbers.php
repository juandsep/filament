<?php

namespace Vibefilter\Filament\Support;

/**
 * Formats numbers with the separators of the app's language, taken from the
 * translation files, so the plugin doesn't need the intl extension.
 */
class Numbers
{
    public static function format(int | float $number, int $decimals = 0): string
    {
        return number_format(
            $number,
            $decimals,
            __('vibefilter::vibefilter.number.decimal'),
            __('vibefilter::vibefilter.number.thousands'),
        );
    }

    /**
     * A dollar amount with two significant digits below a cent ($0.0031,
     * $0.000013) and cents above it ($0.27, $3.40).
     */
    public static function money(float $amount): string
    {
        $decimals = $amount > 0 && $amount < 0.01
            ? min(6, (int) -floor(log10($amount)) + 1)
            : 2;

        return static::format($amount, $decimals);
    }
}
