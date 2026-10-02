# Frontend Views & Asset Structure

> Generated after the CSS/JS extraction refactor. All inline `<style>` and `<script>` blocks have been moved out of Blade templates into dedicated files under `resources/frontend/`.

---

## Overview

All frontend views extend `layouts.app` and use two custom sections:

| Section | Purpose |
|---|---|
| `@section('head')` | Page-specific CSS (one `@vite()` call) |
| `@section('scripts')` | Inline data-init block + one `@vite()` call |

Global layout styles and scripts are loaded by `app.blade.php` directly via `@vite()`.

---

## Directory Structure

```
resources/
├── frontend/
│   ├── css/
│   │   ├── layouts/
│   │   │   ├── app.css          ← Global layout styles (602 lines)
│   │   ├── index.css            ← Homepage (805 lines)
│   │   ├── auth/
│   │   │   ├── login.css          ← Login page (87 lines)
│   │   │   ├── register.css       ← Register page (37 lines)
│   │   │   └── password-reset.css ← Forgot/reset password pages
│   │   ├── shared/
│   │   │   ├── autocomplete.css ← Dropdown + search-input styles (replaces the CDN awesomplete.css and 3 per-page copies); loaded by layouts/app.blade.php
│   │   │   └── charts.css       ← Styles for shared/charts.js (legend) and shared/svgcharts.js (donut, bars)
│   │   │   ├── watchlist.css    ← ★ follow buttons (.wl-star, .wl-btn, .wl-toggle)
│   │   │   ├── skeleton.css     ← Shimmering grey placeholders (.sk, .sk-text, .sk-bar, .sk-chart, .sk-row) + .is-loading-soft; honours reduced motion
│   │   ├── company/
│   │   │   └── show.css         ← Company profile page
│   │   ├── funds/
│   │   │   └── funds.css        ← Fund catalog / detail / compare pages
│   │   ├── exchange_rate/
│   │   │   └── index.css        ← Exchange rate page (799 lines)
│   │   ├── gold/
│   │   │   └── gold.css         ← Gold price page (gold accent KPI cards, tables, chart, calculator)
│   │   ├── news/
│   │   │   └── index.css        ← News list page (hero, grid, cards, sidebar, badges)
│   │   ├── stock/
│   │   │   ├── stock.css        ← Stock detail page (631 lines)
│   │   │   ├── compare.css      ← Stock compare page (156 lines)
│   │   │   └── screener.css     ← Stock screener page
│   │   ├── portfolio/
│   │   │   ├── add-stock.css    ← Add stock to portfolio (25 lines)
│   │   │   ├── create.css       ← Create portfolio (22 lines)
│   │   │   ├── edit.css         ← Edit portfolio (25 lines)
│   │   │   ├── index.css        ← Portfolio list (23 lines)
│   │   │   └── show.css         ← Portfolio detail (20 lines)
│   │   └── profile/
│   │       └── show.css         ← Profile page (17 lines)
│   └── js/
│       ├── layouts/
│       │   ├── app.js           ← Global JS: AOS, NProgress, toast, AI chat (130 lines)
│       ├── index.js             ← Homepage JS (192 lines)
│       ├── auth/
│       │   ├── login.js         ← Login JS (11 lines)
│       │   ├── register.js      ← Register JS (6 lines)
│       │   └── verify-email.js  ← Email verification JS (11 lines)
│       ├── shared/
│       │   ├── autocomplete.js  ← `stockAutocomplete(input, {onPick,onResults})`: Awesomplete (npm) + DOM-built rows, single hover/keyboard highlight, debounced+abortable fetch, clear button, spinner
│       │   ├── charts.js        ← Lightweight Charts setup: theme, `makeChart`, legend, helpers (toDay/cleanSeries/sliceByDays/rebase)
│       │   ├── indicators.js    ← SMA/EMA/RSI/MACD/Bollinger (pure, unit-tested)
│       │   ├── svgcharts.js     ← Dependency-free donut + diverging bars (what Lightweight Charts cannot draw)
│       │   ├── pricefx.js       ← Number cues: `countUp()` from the previous value, `flash()` once in the move's colour (both skipped for reduced motion) + pure helpers (`direction`, `valueAt`, `formatNumber`), unit-tested
│       │   └── toast.js         ← Toast helper for page scripts
│       ├── company/
│       │   └── show.js          ← Company profile: loader, refresh, tabs, donuts
│       ├── funds/
│       │   ├── index.js         ← Catalog: pick up to 4 funds → compare bar
│       │   ├── show.js          ← NAV baseline chart, allocation donut, holdings
│       │   ├── compare.js       ← Rebased NAV lines, return bars, best-of-row table
│       │   └── format.js        ← Fund-page formatting/range helpers
│       ├── exchange_rate/
│       │   └── index.js         ← Exchange rate JS + key-rates bars (svgcharts)
│       ├── gold/
│       │   └── index.js         ← Gold chart (Lightweight Charts step lines), lượng/chỉ toggle, calculator, refresh
│       ├── stock/
│       │   ├── pricetable.js    ← Price history table: pure row model (change vs previous session, volume bar, busy-day flag), row/page markup, page window — unit-tested
│       │   ├── stock.js         ← Stock chart JS: Lightweight Charts candles/area + volume + MA/Bollinger overlays + RSI/MACD panes
│       │   └── compare.js       ← Stock compare JS: Lightweight Charts multi-line, re-based to a common start date
│       └── portfolio/
│           ├── add-stock.js     ← Add stock form JS (50 lines)
│           └── show.js          ← Portfolio chart JS (75 lines)
```

---

## Blade → Asset Mapping

| Blade View | CSS File | JS File |
|---|---|---|
| `layouts/app.blade.php` | `css/layouts/app.css` | `js/layouts/app.js` |
| `layouts/admin.blade.php`, `backend/auth/login.blade.php` and every `backend/**` view | `css/admin/app.css` | `js/admin/app.js` (+ `js/admin/helpers.js`) |
| `index.blade.php` | `css/index.css` | `js/index.js` |
| `auth/login.blade.php` | `css/auth/login.css` | `js/auth/login.js` |
| `auth/register.blade.php` | `css/auth/register.css` | `js/auth/register.js` |
| `auth/forgot-password.blade.php` | `css/auth/password-reset.css` | *(none)* |
| `auth/reset-password.blade.php` | `css/auth/password-reset.css` | *(none, inline `togglePwd` script)* |
| `auth/verify-email.blade.php` | *(none)* | `js/auth/verify-email.js` |
| `exchange_rate/index.blade.php` | `css/exchange_rate/index.css` | `js/exchange_rate/index.js` |
| `gold/index.blade.php` | `css/gold/gold.css` + `css/shared/charts.css` | `js/gold/index.js` |
| `index.blade.php` (home) | `css/index.css` + `css/market/market.css` + `css/shared/watchlist.css` + `css/shared/charts.css` | `js/index.js` + `js/market/home.js` |
| `partials/market-overview.blade.php`, `partials/ticker-items.blade.php` | *(home / layout)* | *(home.js)* |
| `watchlist/index.blade.php` | `css/watchlist/watchlist.css` + `css/shared/watchlist.css` | `js/watchlist/index.js` |
| `news/index.blade.php` | `css/news/index.css` | *(none)* |
| `stock/stock.blade.php` | `css/stock/stock.css` + `css/shared/charts.css` + `css/shared/watchlist.css` + `css/shared/skeleton.css` | `js/stock/stock.js` (+ `shared/pricefx.js`, `stock/pricetable.js`) |
| `stock/compare.blade.php` | `css/stock/compare.css` | `js/stock/compare.js` |
| `stock/screener.blade.php` | `css/stock/screener.css` | *(none)* |
| `company/show.blade.php` | `css/company/show.css` + `css/shared/charts.css` | `js/company/show.js` |
| `funds/index.blade.php` | `css/funds/funds.css` | `js/funds/index.js` |
| `funds/show.blade.php` | `css/funds/funds.css` + `css/shared/charts.css` | `js/funds/show.js` |
| `etf/index.blade.php` | `css/funds/funds.css` + `css/etf/etf.css` + `css/shared/watchlist.css` | `js/etf/index.js` (+ `js/shared/watchlist-init.js`) |
| `etf/show.blade.php` | `css/funds/funds.css` + `css/etf/etf.css` + `css/shared/charts.css` + `css/shared/watchlist.css` | `js/etf/show.js` (+ `js/shared/watchlist-init.js`) |
| `funds/compare.blade.php` | `css/funds/funds.css` + `css/shared/charts.css` | `js/funds/compare.js` |
| `portfolio/index.blade.php` | `css/portfolio/portfolio.css` | `js/portfolio/index.js` |
| `portfolio/create.blade.php`, `portfolio/edit.blade.php`, `portfolio/choose.blade.php` | `css/portfolio/portfolio.css` | *(none)* |
| `portfolio/show.blade.php` | `css/portfolio/portfolio.css` + `css/shared/charts.css` | `js/portfolio/show.js` |
| `portfolio/add-stock.blade.php` | `css/portfolio/portfolio.css` | `js/portfolio/add-stock.js` |
| `profile/show.blade.php` | `css/profile/show.css` | *(none)* |
| `profile/edit.blade.php` | *(none — uses global `layouts/app.css` only)* | *(none)* |

---

## How Blade Variables Are Passed to JS

Extracted `.js` files are pure JavaScript — no Blade syntax. When a page requires PHP server data in JS, a small inline `<script>` block in the Blade file initialises the variables before the `@vite()` call loads the external JS:

### Example — stock/stock.blade.php
```blade
@section('scripts')
<script>
const rawData = @json($data);        {{-- PHP array → JS const --}}
const stockSymbol = '{{ $symbol }}';
</script>
@vite('resources/frontend/js/stock/stock.js')
@endsection
```

### Example — exchange_rate/index.blade.php
```blade
@section('scripts')
<script>
window._exchangeRateUrl = '{{ route("exchange-rate.index") }}';
</script>
@vite('resources/frontend/js/exchange_rate/index.js')
@endsection
```

### CSRF Token
CSRF is never embedded via `{{ csrf_token() }}` in JS files. Use the meta tag instead:
```js
document.querySelector('meta[name="csrf-token"]').getAttribute('content')
```
The meta tag is always present in `layouts/app.blade.php`.

---

## vite.config.js Entry Points

All CSS and JS files under `resources/frontend/` are **automatically collected** via `collectFiles()` in `vite.config.js`. When adding a new page asset:

1. Create `resources/frontend/css/[page].css` and/or `resources/frontend/js/[page].js`
2. **No manual entry needed in `vite.config.js`** — the file is auto-discovered.
3. In the Blade view, add to `@section('head')` and `@section('scripts')`:
   ```blade
   @section('head')
   @vite('resources/frontend/css/[page].css')
   @endsection

   @section('scripts')
   @vite('resources/frontend/js/[page].js')
   @endsection
   ```
4. Run `node node_modules/vite/bin/vite.js build` (or `npm run build` if execution policy allows)

---

## Global Assets (loaded on every page)

Loaded automatically by `layouts/app.blade.php` — no action needed in individual views:

| Asset | Description |
|---|---|
| `resources/frontend/css/layouts/app.css` | CSS variables, navbar, hero, cards, tables, badges, buttons, NProgress, AOS, AI chat, footer |
| `resources/frontend/js/layouts/app.js` | AOS.init, NProgress, back-to-top, `showToast()`, AI chat open/close/send, language switcher, counter-up animation |

### CSS Variables (defined in `layouts/app.css`)
```css
:root {
  --primary-blue:    #2563eb;
  --secondary-blue:  #3b82f6;
  --success-green:   #10b981;
  --danger-red:      #ef4444;
  --warning-orange:  #f59e0b;
  --light-blue:      #eff6ff;
  --dark-navy:       #0f172a;
}
```

---

## CDN Dependencies (loaded via layouts/app.blade.php `<head>`)

| Library | Version | Purpose |
|---|---|---|
| Bootstrap | 4.5.2 | CSS framework |
| Bootstrap Icons | 1.11.3 | Icon set |
| Lightweight Charts (TradingView) | 5.x — **npm dependency, bundled by Vite** (no CDN request at runtime) | Time-series charts: stock candles/line/volume/indicator panes, stock & fund comparison, fund NAV. Apache-2.0 — keep the built-in TradingView attribution logo (`layout.attributionLogo`) |
| `shared/svgcharts.js` | in-repo | Donut + horizontal bars (Lightweight Charts is time-series only) |
| AOS | 2.3.4 | Animate on scroll |
| NProgress | 0.2.0 | Page loading bar |
| Awesomplete | 1.1.5 | Symbol autocomplete (stock pages) |
| Inter | Google Font | Typography |

> **Note**: The **frontend** (this doc's scope, `layouts/app.blade.php`) uses Bootstrap **4.5.2** — do not use Bootstrap 5 class names or JS APIs here. The separate **admin panel** (`layouts/admin.blade.php`) uses Tabler 1.5 / Bootstrap 5.3 **bundled locally** instead (see "Admin design system" below); see [docs/RBAC.md](RBAC.md) and [docs/STRUCTURE.md](STRUCTURE.md) for backend views.

---

## Build Command

```bash
# Recommended (avoids PowerShell execution policy issues on Windows)
node node_modules/vite/bin/vite.js build

# Alternative (if npm ps1 execution policy is enabled)
npm run build
```

## Chart conventions

- **Time-series → `shared/charts.js`** (`makeChart(el)` gives the house look: Inter font, dotted grid, dashed crosshair, `vi-VN` dates). **Category charts (donut/bars) → `shared/svgcharts.js`.** Do not add another chart library or a CDN `<script>` for charts.
- Daily bars use `'YYYY-MM-DD'` times via `toDay(ms)` — its +12h shift handles both midnight-UTC and midnight-Vietnam timestamps.
- Set price formats **per series** (`PRICE_FORMAT`, `DEC_FORMAT`), never a chart-wide `priceFormatter` (it would also reformat RSI/MACD/volume).
- Extra indicator panes are extra **panes of the same chart** (`chart.addSeries(Series, opts, paneIndex)`), so the time axis and crosshair stay in sync; removing a pane's last series removes the pane.
- A chart created inside a hidden container is re-fitted automatically on its first real size (`makeChart`).
- Pure logic (indicators, series helpers) stays DOM-free and is covered by `npm test` (see docs/TESTING.md).

## Home page layout ("data first")

- Order: compact hero (`.hero-search.hero-compact`: title, subtitle, search card, popular tickers, "So sánh" chip, Ctrl+K hint) → `partials/market-overview` → AI prediction button → featured stocks → exchange rates → hot industries → news → `@guest` product pitch → `@guest` sign-up banner. Working data comes first; marketing is last and only for visitors who are not signed in.
- The compact rules are appended to `css/index.css` under `.hero-compact`; the original `.search-card` / `.hero-*` rules stay as the base (the card no longer overlaps the hero with a negative margin). On a phone the input and the search button share a row and the button shows only its icon.
- **Background and motion**: the hero is one even blue (`#2563eb → #3b82f6`) so `.mk::before` (market.css) can fade the same blue out behind the market title and the index cards; the white-on-blue title/status styles live in `.mk-head`. `.home-body` wraps the market and everything below with ONE continuous gradient (colour-wheel scheme documented in `index.css`: analogous blues to violet + a small amber complement; change hues together, keep saturation soft), a dot grid (`::before`) and five `.home-glow` blobs; cards inside it are frosted glass (`.home-body .mk-card/.mk-idx/.info-section/.stock-card/.news-card`); do not add a second background zone, it reads as separate pieces; `.hero-fx` holds the hero orbs and candle bars. Motion (cards rising, `clip-path` reveals of sparklines/chart/breadth bar, bar growth, glow drift) is declared only inside `@media (prefers-reduced-motion: no-preference)`; use `animation-fill-mode: backwards` (not `both`) on anything that also has a hover transform. `home.js` counts the index levels and breadth numbers up on load and flashes (`pricefx.flash`) the numbers a 60 s poll changed. Decorative elements are `aria-hidden`.
- `js/index.js` binds to `#symbol`, `.search-form-wrapper`, `.search-btn`, `.btn-text`, `#notFoundMsg`: keep those hooks when restyling (covered by the `homeLayout` tests).

## Stock page: units, header, history table, skeletons
- **Units — never print a feed price as money.** `stock_prices` is quoted in thousands of VND (FPT 62.1 = 62,100 ₫); an index (`VNINDEX`, `VN30`…) is in points. `App\Support\StockQuoteSummary::build($rows, $symbol)` converts once, server-side, and returns the whole header model (close, change, day and 52-week range with position, volume vs 20-session average, 1T/3T/6T/1N returns, `scale`/`decimals`/`unit`). The page passes `const stockUnit = {scale, decimals, unit}` to the script, which multiplies the bars by `scale` so the chart axis, legend, table and header all read 62.100, and an index keeps 1.737,71. A new page that shows a price from `stock_prices` must go through `PriceUnit` / `StockQuoteSummary`, not format the raw number.
- **Header** (`.sq-*` in `stock.css`): symbol + exchange (`VnFormat::exchange`: HSX → HOSE), company and industry, one row of actions (`.wl-toggle` keeps its hook), big price, change chip, day/52-week range bars, key-figure tiles. The old blue banner (`.stock-header`, `.back-button`) is gone.
- **History table** (`.px-*`): built in the browser from `pricetable.js` — close, change in dong and %, open/high/low, volume with a thin bar (amber when ≥ 1.5× the average), no currency column, sticky header (the wrapper uses `overflow-x: clip` so sticking still works; never `overflow: auto`). The finance table keeps `.data-table`. On a phone open/high/low are hidden.
- **Skeletons** (`css/shared/skeleton.css`): the server renders `.sk-chart` inside `#priceChart` and ten `.sk-row`s in the table body; `stock.js` removes/replaces them when it has the data. The finance block shows skeleton rows on first load and dims the old table (`.is-loading-soft`) when switching tab/period instead of a spinner. Use the same classes for any new "loading" state.
- **Number cues** (`shared/pricefx.js`): the header price counts up from the previous close and flashes once red/green; the markup already holds the final text (right without JS, and for `prefers-reduced-motion`). Reuse `countUp`/`flash` where a number is updated live.

## ETF pages

- The ETF pages reuse the fund look (`fd-*` classes in `css/funds/funds.css`); `css/etf/etf.css` only adds the explainer, the index/manager tags and the detail extras. Do not duplicate the fund styles.
- Prices are shown in **whole VND**. The embedded chart series is in feed units (thousands), so `js/etf/show.js` multiplies by 1000; the range switch slices client-side with `sliceByDays`.
- The compare picker sends the ticked symbols to the existing `/stock/compare?symbols=` page (it accepts any listed symbol), instead of a second compare implementation.
- A Blade directive must not touch a word: `tuần@if(...)` is not parsed as `@if` but its `@endif` is, which is a syntax error — put a space before inline directives.

## Search autocomplete conventions

- Every symbol search box (home, `/stock`, `/stock/compare`) uses `shared/autocomplete.js` — do not instantiate `Awesomplete` directly and do not restyle `.awesomplete` in page CSS (all styling is in `css/shared/autocomplete.css`, loaded globally by the layout).
- **One highlighted row at a time**: keyboard selection and mouse hover are the same state (`aria-selected`), styled in brand blue with a solid ticker chip and a left accent bar. There is intentionally no separate `:hover` colour — two competing highlights was the original UX bug. Hover moves the selection *without* Awesomplete's `goto()` because that call also scrolls the list.
- Rows are built with DOM nodes (`textContent`), never HTML strings; matched text uses a soft `<mark>` tint, not the CDN's neon yellow.
- The endpoint `GET /stocks-list?q=` returns `{symbol,name,exchange}` ranked server-side (exact ticker, prefix, contains, name); the client therefore sets `filter: () => true, sort: false`.

## Admin layout (Tabler) gotchas

- (superseded) The sidebar no longer has collapsible groups: it is flat with section labels, see "Admin design system".
- Tabler makes `.navbar-nav .nav-link .badge` **`position:absolute`** (notification dot). A text badge inside a nav link needs `position: static !important` (see `.account-meta .badge`).

## Portfolio UI conventions

- One stylesheet, `css/portfolio/portfolio.css` (`pf-*`): buttons are `pf-btn` + one variant (`pf-btn-primary` for THE main action of a page, `-soft` secondary, `-ghost` neutral, `-danger` / `-danger-solid`), `pf-btn-icon` for row actions; a loading button gets `.is-loading` (spinner replaces its icon). Do not add inline `style=` colours or Bootstrap `btn-*` classes to these pages.
- Destructive actions use Bootstrap **modals** (never `confirm()`), and every handler is attached with `addEventListener` in the page's module — an ES module's functions are not global, so inline `onclick="fn()"` silently does nothing (this is why the old "Cập nhật giá" / edit buttons were dead).
- Money is **whole VND** everywhere in the UI and DB; the price feed is thousands of VND — convert only through `App\Support\PriceUnit`.

### Portfolio insights (show page)
- `PortfolioInsightsService::enhance()` runs in the controller and overrides `performance`, `holdings`, `suggestions`; it adds `risk`, `benchmarks`, `sectors`. Every card has an honest empty state (no ledger, fewer than 20 sessions, benchmark history too short) — never a number from too little data.
- The orange "Nếu mua <index>" line on the performance chart is the SAME cash flows invested in the index (units bought at each buy, sold at each sell), not two return lines: it answers "did my choices beat just buying the index?". `window.__PF__.benchmarks[symbol].series` shares its dates with `performance` (same thinning).
- Colour a number by its sign, never by its rank: the "best" mover of a losing portfolio is still red.
- The trade modal disables Submit when `tradePreview()` returns `blocked`; server validation messages are Vietnamese (`storeTransaction`).

### Market widgets, watchlist and ledger (frontend conventions)
- **First paint is server-rendered, then JS takes over**: the market section's index cards, breadth and liquidity come from Blade; the movers table and the watchlist card are rendered by `js/market/home.js` from `window.__MARKET__` (JSON) so tabs/exchange chips need no round trip, and the same renderer applies the 60 s poll (`GET /market/data`, only while the market is open and the tab is visible).
- **★ buttons** are plain `data-watch="SYMBOL"` elements handled by ONE delegated listener in `js/shared/watchlist.js` (`initWatchlistStars({auth, watched})`, `paintStars(root)` after rendering rows, `watchlist:change` event). Stock/company pages load it through `js/shared/watchlist-init.js` + `window.__WATCH__`.
- **Pure helpers are unit-tested in Node**: `js/market/format.js` (number/percent/value formatting, `esc`, `exchangeLabel`) and `js/portfolio/ledger.js` (fee estimate 0.15 % buy / 0.25 % sell incl. tax, trade preview). Keep DOM code out of them.
- **Trade modal** (`portfolio/show.blade.php`, `css/portfolio/ledger.css`): one form for buy and sell; opened from the header button (free symbol + autocomplete) or the per-holding ➕/💵 buttons (symbol locked, price prefilled). The fee is auto-estimated until the user edits it.

## Admin design system (`layouts/admin.blade.php`, `css/admin/app.css`, `js/admin/app.js`)

**Stack**: `@tabler/core` 1.5.1 (Bootstrap 5.3, MIT) + `@tabler/icons-webfont` + `@fontsource-variable/inter` (Vietnamese subset included), all imported by `css/admin/app.css` and bundled by Vite — no CDN request at runtime (the old shell fetched Tabler 1.0 from jsDelivr). Icons are `<i class="ti ti-…">`; inline SVG icons are gone from the admin views.

**Shell**: one navigation array in the layout drives BOTH the sidebar and the command palette, each entry behind its Gate (`manage-users`, `manage-roles`, `manage-permissions`, `manage-queue`, `manage-features`, `view-timeline`). The queue link carries a failed-jobs badge (`failed_jobs` count, cached 30 s, never breaks the page). The sidebar collapses to icons on desktop (`ad-sidebar` in localStorage), the theme is light / dark / auto (`tabler-theme` in localStorage, applied by an inline `<head>` script before first paint — no light flash).

**Behaviour** (`js/admin/app.js`, pure helpers in `helpers.js` are unit-tested in Node): Ctrl/⌘+K or `/` opens a command palette (accent-insensitive: "quan ly" finds "Quản lý"); `window.adToast(message, type)`; server flash messages are rendered as hidden `[data-flash]` nodes and shown as toasts; `window.adConfirm(message, {title, ok, tone})` returns a Promise; `<form data-confirm="…">` / `<button data-confirm="…">` ask before submitting (replaces every `onsubmit="return confirm(...)"`); `window.bootstrap` still exposes Modal/Dropdown/Collapse/… for page scripts. Page scripts pushed with `@push('scripts')` run before the module, so call these helpers from event handlers, not at load.

**Conventions**: page header = `@section('page_pretitle'|'page_title'|'page_actions'|'breadcrumbs')` (breadcrumb sections emit `<i class="ti ti-chevron-right"></i><a…>` / `<span class="current">…</span>`); cards `.card` with `.card-header` = title left / actions right; tables `.table.table-vcenter.table-hover.card-table` with `.ad-avatar-cell`, `.ad-dot ok|warn|bad|off` status dots and `.table-row-actions` icon buttons; KPI tiles `.ad-stat` + `.ad-stat-icon blue|green|purple|orange|red|cyan`; toolbars `.ad-toolbar`; empty states `.empty`. Colours come from CSS variables (`--ad-surface`, `--ad-line`, `--ad-muted`, `--ad-ink`…) redefined under `[data-bs-theme=dark]`, so new components need no dark-mode rules of their own.
