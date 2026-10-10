<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** The home page polish layer (mesh gradient, spotlight cards, tab ink, AI spring): wired in, and motion kept behind the reduced-motion guard. */
#[Group('homeFx')]
class HomeFxAssetsTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_the_hero_carries_the_four_mesh_blobs_and_the_page_loads_the_new_assets(): void
    {
        $view = $this->read('resources/views/index.blade.php');

        $this->assertSame(4, preg_match_all('/<i class="mesh m\d"><\/i>/', $view));
        $this->assertStringContainsString('resources/frontend/css/home/home-fx.css', $view);
        $this->assertStringContainsString('./home/home-fx.js', $this->read('resources/frontend/js/index.js'));
    }

    public function test_every_infinite_animation_sits_inside_the_no_preference_guard(): void
    {
        $css = $this->read('resources/frontend/css/home/home-fx.css');

        $this->assertStringContainsString('@keyframes blob', $css);
        $guarded = substr($css, strpos($css, '@media (prefers-reduced-motion: no-preference)'));
        $unguarded = substr($css, 0, strlen($css) - strlen($guarded));

        $this->assertStringContainsString('animation: blob', $guarded);
        $this->assertStringNotContainsString('infinite', $unguarded);
        $this->assertStringContainsString('cubic-bezier(.34, 1.56, .64, 1)', $guarded);   // the spring
    }

    public function test_the_glow_is_decoration_only_and_never_catches_the_pointer(): void
    {
        $css = $this->read('resources/frontend/css/home/home-fx.css');

        $this->assertMatchesRegularExpression('/\.fx-ring, \.fx-spot \{[^}]*pointer-events: none/', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $this->read('resources/frontend/js/home/home-fx.js'));
    }

    public function test_the_hero_has_water_particles_and_scroll_driven_depth_that_only_run_with_motion_allowed(): void
    {
        $view = $this->read('resources/views/index.blade.php');
        $css = $this->read('resources/frontend/css/home/home-fx.css');

        $this->assertStringContainsString('class="hero-wave"', $view);
        $this->assertSame(3, preg_match_all('/<path class="w\d"/', $view));
        $this->assertStringContainsString('class="pt"', $view);
        $this->assertMatchesRegularExpression('/@supports \(animation-timeline: scroll\(\)\) \{\s*@media \(prefers-reduced-motion: no-preference\)/', $css);
        $this->assertStringContainsString('.hero-search.is-paused', $css);
        $this->assertStringContainsString('initHeroPause', $this->read('resources/frontend/js/home/home-fx.js'));
    }
}
