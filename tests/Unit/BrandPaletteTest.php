<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Workspaces\Application\BrandPalette;
use PHPUnit\Framework\TestCase;

/** Hex → full --brand-* scale for per-workspace white-label theming. */
final class BrandPaletteTest extends TestCase
{
    public function test_valid_hex_detection(): void
    {
        $this->assertTrue(BrandPalette::isValidHex('#2563eb'));
        $this->assertTrue(BrandPalette::isValidHex('2563eb'));
        $this->assertFalse(BrandPalette::isValidHex('blue'));
        $this->assertFalse(BrandPalette::isValidHex('#123'));
    }

    public function test_invalid_hex_yields_no_style(): void
    {
        $this->assertSame('', BrandPalette::styleVars('not-a-color'));
        $this->assertSame('', BrandPalette::styleVars(''));
    }

    public function test_builds_the_full_brand_scale_with_base_at_600(): void
    {
        $css = BrandPalette::styleVars('#2563eb'); // r=37 g=99 b=235

        // Every Tailwind step is present.
        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $step) {
            $this->assertStringContainsString("--brand-{$step}:", $css);
        }
        // 600 is the base colour exactly (ratio 0).
        $this->assertStringContainsString('--brand-600: 37 99 235;', $css);
        // 50 is a near-white tint; 950 is a dark shade.
        $this->assertMatchesRegularExpression('/--brand-50: 2[0-9]{2} 2[0-9]{2} 2[0-9]{2};/', $css);
    }
}
