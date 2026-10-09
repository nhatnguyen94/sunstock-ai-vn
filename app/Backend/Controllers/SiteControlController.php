<?php

namespace App\Backend\Controllers;

use App\Models\StockSymbol;
use App\Support\ActivityLogger;
use App\Support\SiteSettings;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin > Giao diện & Cache: which blocks the home page shows, the site-wide notice, and clearing the caches that feed the public pages. */
class SiteControlController extends Controller
{
    /** Caches an admin may clear by name: group => [label, what it holds]. The keys behind each group are in {@see self::cacheKeys()}. */
    public const CACHE_GROUPS = [
        'home' => ['Dữ liệu trang chủ', 'Cổ phiếu nổi bật, tỷ giá trong ngày, tin tức, dải giá chạy, lịch sự kiện — dựng lại ở lần mở trang kế tiếp.'],
        'ai' => ['Dự đoán thị trường tuần này', 'Bản dự đoán AI được lưu 2 giờ: xóa để lượt bấm kế tiếp hỏi lại AI (tốn một lượt gọi).'],
        'screener' => ['Bộ lọc cổ phiếu', 'Các chỉ số tài chính dùng cho trang lọc cổ phiếu (lưu 1 giờ).'],
    ];

    public function index(): View
    {
        Gate::authorize('manage-features');

        return view('backend.site.index', [
            'blocks' => SiteSettings::homeBlocks(),
            'labels' => SiteSettings::HOME_BLOCKS,
            'announcement' => SiteSettings::announcement(),
            'levels' => SiteSettings::ANNOUNCEMENT_LEVELS,
            'cacheGroups' => self::CACHE_GROUPS,
            'featured' => SiteSettings::featuredSymbols(),
            'maxFeatured' => SiteSettings::MAX_FEATURED,
        ]);
    }

    public function updateFeatured(Request $request): RedirectResponse
    {
        Gate::authorize('manage-features');

        $request->validate(['symbols' => 'required|string|max:120'], ['symbols.required' => 'Nhập ít nhất một mã cổ phiếu.']);

        // "fpt, vnm acb" → [FPT, VNM, ACB]: letters and digits only, no repeats, in the order typed
        $symbols = collect(preg_split('/[\s,;]+/', strtoupper((string) $request->input('symbols')), -1, PREG_SPLIT_NO_EMPTY))->unique()->values();

        if ($symbols->isEmpty() || $symbols->count() > SiteSettings::MAX_FEATURED || $symbols->contains(fn ($s) => ! preg_match('/^[A-Z0-9]{1,12}$/', $s))) {
            return back()->withInput()->withErrors(['symbols' => 'Nhập từ 1 đến '.SiteSettings::MAX_FEATURED.' mã, mỗi mã gồm chữ và số, cách nhau bằng dấu phẩy hoặc khoảng trắng.']);
        }
        $unknown = $symbols->reject(fn ($s) => StockSymbol::where('symbol', $s)->exists());
        if ($unknown->isNotEmpty()) {
            return back()->withInput()->withErrors(['symbols' => 'Không có mã: '.$unknown->implode(', ').' (chỉ chọn mã có trong danh sách cổ phiếu của hệ thống).']);
        }

        $before = SiteSettings::featuredSymbols();
        SiteSettings::set('home.featured', $symbols->all());
        ActivityLogger::log('admin_action', 'Đổi cổ phiếu nổi bật ở trang chủ', ['before' => $before, 'after' => $symbols->all()]);

        return TransformerResponse::backSuccess('Trang chủ sẽ hiện: '.$symbols->implode(', ').'.');
    }

    public function updateBlocks(Request $request): RedirectResponse
    {
        Gate::authorize('manage-features');

        $request->validate(['blocks' => 'nullable|array', 'blocks.*' => 'boolean']);

        $before = SiteSettings::homeBlocks();
        SiteSettings::saveHomeBlocks((array) $request->input('blocks', []));
        $after = SiteSettings::homeBlocks();

        ActivityLogger::log('admin_action', 'Đổi các khối hiển thị của trang chủ', ['hidden' => array_keys(array_filter($after, fn ($on) => ! $on)), 'was_hidden' => array_keys(array_filter($before, fn ($on) => ! $on))]);
        $this->forgetHomeCaches();   // the home page reads its blocks fresh, but its cached parts may belong to a block that was just turned on

        return TransformerResponse::backSuccess('Đã lưu các khối hiển thị của trang chủ.');
    }

    public function updateAnnouncement(Request $request): RedirectResponse
    {
        Gate::authorize('manage-features');

        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'level' => ['required', Rule::in(array_keys(SiteSettings::ANNOUNCEMENT_LEVELS))],
            'text' => 'nullable|string|max:300',
            // only this site (/path) or an https address: never javascript:, data: or a protocol-relative //host
            'url' => ['nullable', 'string', 'max:255', 'regex:#^(https://[^\s]+|/(?!/)[^\s]*)$#'],
            'link_text' => 'nullable|string|max:40',
        ], ['url.regex' => 'Liên kết phải bắt đầu bằng https:// hoặc là đường dẫn trong trang (ví dụ /stock).']);

        $enabled = $request->boolean('enabled');
        if ($enabled && trim((string) ($data['text'] ?? '')) === '') {
            return back()->withInput()->withErrors(['text' => 'Nhập nội dung thông báo trước khi bật.']);
        }

        SiteSettings::set('announcement', [
            'enabled' => $enabled,
            'level' => $data['level'],
            'text' => trim((string) ($data['text'] ?? '')),
            'url' => trim((string) ($data['url'] ?? '')),
            'link_text' => trim((string) ($data['link_text'] ?? '')),
        ]);

        ActivityLogger::log('admin_action', $enabled ? 'Bật thông báo toàn trang' : 'Tắt thông báo toàn trang', ['level' => $data['level']]);

        return TransformerResponse::backSuccess($enabled ? 'Thông báo đang hiển thị trên mọi trang công khai.' : 'Đã lưu và tắt thông báo.');
    }

    public function clearCache(string $group): RedirectResponse
    {
        Gate::authorize('manage-features');
        TransformerResponse::abortUnless(isset(self::CACHE_GROUPS[$group]), TransformerResponse::HTTP_NOT_FOUND);

        foreach ($this->cacheKeys($group) as $key) {
            Cache::forget($key);
        }

        ActivityLogger::log('admin_action', 'Xóa cache: '.self::CACHE_GROUPS[$group][0], ['group' => $group]);

        return TransformerResponse::backSuccess('Đã xóa cache “'.self::CACHE_GROUPS[$group][0].'”.');
    }

    private function forgetHomeCaches(): void
    {
        foreach ($this->cacheKeys('home') as $key) {
            Cache::forget($key);
        }
    }

    /** @return string[] */
    private function cacheKeys(string $group): array
    {
        $today = Carbon::now()->format('Y-m-d');

        return match ($group) {
            'home' => [SiteSettings::featuredCacheKey(), "exchange_rates_home_{$today}", 'homepage_news', 'frontend_news_categories', 'market:ticker', "home:events:v1:{$today}"],
            'ai' => ['ai_market_predict_v2_'.date('oW')],
            'screener' => ['screener_ratio_metrics'],
            default => [],
        };
    }
}
