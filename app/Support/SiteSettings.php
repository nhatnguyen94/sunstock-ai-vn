<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The few things an admin may switch without a deploy: which blocks the home page shows, the announcement bar, the AI limits.
 * Values live in `site_settings` (key => JSON) behind a one-minute cache; every reader falls back to the defaults below, so a
 * missing table or an empty row never breaks a page.
 */
class SiteSettings
{
    /** Blocks of the home page an admin may hide: key => label (the label is what the admin screen shows). */
    public const HOME_BLOCKS = [
        'world' => 'Dải chỉ số thế giới',
        'heatmap' => 'Bản đồ nhiệt',
        'brief' => 'Bản tin phiên',
        'foreign' => 'Khối ngoại',
        'sentiment' => 'Chỉ báo tâm lý',
        'pulse' => 'Vàng · Tỷ giá · Quỹ',
        'signals' => 'Tín hiệu hôm nay',
        'events' => 'Sự kiện sắp tới',
        'news' => 'Tin tức',
    ];

    public const ANNOUNCEMENT_LEVELS = ['info' => 'Thông tin', 'warning' => 'Cảnh báo', 'danger' => 'Khẩn cấp'];

    private const CACHE_KEY = 'site-settings:all';

    /** @return array<string, mixed> */
    private static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, fn () => SiteSetting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return [];   // the table may not exist yet (fresh checkout, tests without a migration)
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, bool> every block of the home page with its on/off state (on unless an admin switched it off) */
    public static function homeBlocks(): array
    {
        $stored = (array) self::get('home.blocks', []);

        return collect(self::HOME_BLOCKS)->map(fn ($label, $key) => (bool) ($stored[$key] ?? true))->all();
    }

    /** @param array<string, mixed> $on keys of the blocks that stay visible (anything unknown is ignored) */
    public static function saveHomeBlocks(array $on): void
    {
        self::set('home.blocks', collect(self::HOME_BLOCKS)->map(fn ($label, $key) => ! empty($on[$key]))->all());
    }

    /** @return array{enabled: bool, level: string, text: string, url: string, link_text: string} */
    public static function announcement(): array
    {
        $a = (array) self::get('announcement', []);
        $level = (string) ($a['level'] ?? 'info');

        return [
            'enabled' => (bool) ($a['enabled'] ?? false),
            'level' => isset(self::ANNOUNCEMENT_LEVELS[$level]) ? $level : 'info',
            'text' => (string) ($a['text'] ?? ''),
            'url' => (string) ($a['url'] ?? ''),
            'link_text' => (string) ($a['link_text'] ?? ''),
        ];
    }

    /** The bar to show on every public page, or null when there is nothing to say. */
    public static function activeAnnouncement(): ?array
    {
        $a = self::announcement();

        return $a['enabled'] && trim($a['text']) !== '' ? $a : null;
    }

    public const DEFAULT_FEATURED = ['FPT', 'VNM', 'ACB'];

    public const MAX_FEATURED = 6;

    /** @return string[] the stocks of the "Cổ phiếu nổi bật" cards on the home page, in display order */
    public static function featuredSymbols(): array
    {
        $stored = self::get('home.featured');
        $list = is_array($stored) ? array_values(array_filter(array_map(fn ($s) => is_string($s) ? strtoupper(trim($s)) : '', $stored))) : [];

        return $list ? array_slice($list, 0, self::MAX_FEATURED) : self::DEFAULT_FEATURED;
    }

    /** The cache key of the featured cards: it changes with the list, so a new list shows at once. */
    public static function featuredCacheKey(): string
    {
        return 'featured_stocks:'.implode('-', self::featuredSymbols());
    }

    /** @return array{enabled: bool, daily_limit: int} the AI kill switch and the per-account daily quota (0 = no daily quota) */
    public static function ai(): array
    {
        $ai = (array) self::get('ai', []);

        return ['enabled' => (bool) ($ai['enabled'] ?? true), 'daily_limit' => max(0, (int) ($ai['daily_limit'] ?? 0))];
    }
}
