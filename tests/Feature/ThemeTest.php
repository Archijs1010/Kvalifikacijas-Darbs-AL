<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\TrackedSkin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    private const THEMES = ['light', 'dark', 'neon', 'legacy', 'green'];

    #[Test]
    public function the_layout_renders_a_theme_selector_with_every_theme(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="theme-select"', $html);

        foreach (self::THEMES as $theme) {
            $this->assertStringContainsString('value="'.$theme.'"', $html);
        }

        $this->assertStringContainsString('>Light<', $html);
        $this->assertStringContainsString('>Dark<', $html);
        $this->assertStringContainsString('>Neon<', $html);
        $this->assertStringContainsString('>Legacy<', $html);
        $this->assertStringContainsString('>Green<', $html);
    }

    #[Test]
    public function the_page_defaults_to_the_dark_theme_before_scripts_run(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false);
    }

    #[Test]
    public function the_theme_is_persisted_in_localstorage(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('csfloat-theme', $html);
        $this->assertStringContainsString('localStorage.setItem', $html);
        $this->assertStringContainsString('localStorage.getItem', $html);
    }

    #[Test]
    public function the_theme_is_applied_before_the_body_paints(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $applyPosition = strpos($html, "setAttribute('data-theme'");
        $headEnd = strpos($html, '</head>');

        $this->assertNotFalse($applyPosition);
        $this->assertNotFalse($headEnd);
        $this->assertLessThan($headEnd, $applyPosition);
    }

    #[Test]
    public function design_tokens_are_defined_for_every_theme(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        foreach (self::THEMES as $theme) {
            $this->assertStringContainsString('[data-theme="'.$theme.'"]', $html);
        }

        $this->assertStringContainsString(':root', $html);

        foreach ([
            '--surface:',
            '--surface-raised:',
            '--border:',
            '--border-strong:',
            '--text:',
            '--text-muted:',
            '--accent:',
            '--accent-strong:',
            '--success:',
            '--danger:',
            '--warning:',
            '--on-accent:',
        ] as $token) {
            $this->assertStringContainsString($token, $html);
        }
    }

    #[Test]
    public function the_tailwind_palette_is_mapped_onto_the_design_tokens(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('tailwind.config', $html);
        $this->assertStringContainsString('rgb(var(--surface-raised)', $html);
        $this->assertStringContainsString('rgb(var(--text) / <alpha-value>)', $html);
        $this->assertStringContainsString('rgb(var(--accent-strong)', $html);
        $this->assertStringContainsString('rgb(var(--success)', $html);
        $this->assertStringContainsString('rgb(var(--danger)', $html);
        $this->assertStringContainsString('rgb(var(--warning)', $html);
        $this->assertStringContainsString('rgb(var(--on-accent)', $html);
    }

    #[Test]
    public function the_selector_is_available_on_every_page(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);
        $sale = Sale::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);

        $urls = [
            route('dashboard'),
            route('skins.index'),
            route('skins.edit', $skin),
            route('skins.sales', $skin),
            route('sales.index'),
            route('sales.show', $sale),
            route('analytics.index'),
        ];

        foreach ($urls as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="theme-select"', false);
        }
    }

    #[Test]
    public function the_charts_draw_their_colors_from_the_theme_tokens(): void
    {
        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);

        Sale::factory()->count(3)->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);

        $skinSales = $this->get(route('skins.sales', $skin))->assertOk()->getContent();
        $this->assertStringContainsString('CSFloatTheme.color', $skinSales);
        $this->assertStringNotContainsString("'#38bdf8'", $skinSales);
        $this->assertStringNotContainsString("'rgba(255, 255, 255, 0.06)'", $skinSales);
        $this->assertStringContainsString("t('--accent-muted'", $skinSales);

        $analytics = $this->get(route('analytics.index'))->assertOk()->getContent();
        $this->assertStringContainsString('CSFloatTheme.color', $analytics);
        $this->assertStringNotContainsString("'#38bdf8'", $analytics);
        $this->assertStringNotContainsString("'rgba(255, 255, 255, 0.06)'", $analytics);
    }

    #[Test]
    public function changing_the_theme_dispatches_an_event_that_repaints_the_charts(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee("new CustomEvent('themechange'", false);

        $skin = TrackedSkin::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);

        Sale::factory()->create([
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
        ]);

        $this->get(route('skins.sales', $skin))
            ->assertOk()
            ->assertSee("addEventListener('themechange'", false);

        $this->get(route('analytics.index'))
            ->assertOk()
            ->assertSee("addEventListener('themechange'", false);
    }

    #[Test]
    public function existing_pages_still_render_their_core_content(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Import All Sales', false)
            ->assertSee('CSFloat API usage', false);

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Sales History', false);
    }
}
