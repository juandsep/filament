<?php

namespace Vibefilter\Filament\Tests;

use Vibefilter\Filament\Support\TextNormalizer;

class TextNormalizerTest extends TestCase
{
    public function test_evens_out_whitespace_and_line_breaks(): void
    {
        $this->assertSame("Broken box.\nRefund NOW!", TextNormalizer::text("  Broken   box. \r\n\r\n\n  Refund\tNOW!  "));
    }

    public function test_keeps_case_punctuation_and_emoji(): void
    {
        $this->assertSame('I LOVE IT!!! 😍', TextNormalizer::text('I LOVE IT!!! 😍'));
    }

    public function test_unifies_unicode_form(): void
    {
        $decomposed = "Cafe\u{0301}";

        $this->assertSame("Caf\u{00E9}", TextNormalizer::text($decomposed));
    }

    public function test_questions_are_lowercased(): void
    {
        $this->assertSame('the customer is angry.', TextNormalizer::question('  The Customer   is ANGRY. '));
    }
}
