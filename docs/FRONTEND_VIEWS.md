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
│   │   │   └── admin.css        ← Admin layout styles (21 lines)
│   │   ├── index.css            ← Homepage (805 lines)
│   │   ├── auth/
│   │   │   ├── login.css          ← Login page (87 lines)
│   │   │   ├── register.css       ← Register page (37 lines)
│   │   │   └── password-reset.css ← Forgot/reset password pages
│   │   ├── shared/
│   │   │   ├── autocomplete.css ← Dropdown + search-input styles (replaces the CDN awesomplete.css and 3 per-page copies); loaded by layouts/app.blade.php
│   │   │   └── charts.css       ← Styles for shared/charts.js (legend) and shared/svgcharts.js (donut, bars)
│   │   ├── company/
│   │   │   └── show.css         ← Company profile page
│   │   ├── funds/
│   │   │   └── funds.css        ← Fund catalog / detail / compare pages
│   │   ├── exchange_rate/
│   │   │   └── index.css        ← Exchange rate page (799 lines)
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
│       │   └── admin.js         ← Admin layout JS (10 lines)
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
│       ├── stock/
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
| `layouts/admin.blade.php` | `css/layouts/admin.css` | `js/layouts/admin.js` |
| `index.blade.php` | `css/index.css` | `js/index.js` |
| `auth/login.blade.php` | `css/auth/login.css` | `js/auth/login.js` |
| `auth/register.blade.php` | `css/auth/register.css` | `js/auth/register.js` |
| `auth/forgot-password.blade.php` | `css/auth/password-reset.css` | *(none)* |
| `auth/reset-password.blade.php` | `css/auth/password-reset.css` | *(none, inline `togglePwd` script)* |
| `auth/verify-email.blade.php` | *(none)* | `js/auth/verify-email.js` |
| `exchange_rate/index.blade.php` | `css/exchange_rate/index.css` | `js/exchange_rate/index.js` |
| `news/index.blade.php` | `css/news/index.css` | *(none)* |
| `stock/stock.blade.php` | `css/stock/stock.css` | `js/stock/stock.js` |
| `stock/compare.blade.php` | `css/stock/compare.css` | `js/stock/compare.js` |
| `stock/screener.blade.php` | `css/stock/screener.css` | *(none)* |
| `company/show.blade.php` | `css/company/show.css` + `css/shared/charts.css` | `js/company/show.js` |
| `funds/index.blade.php` | `css/funds/funds.css` | `js/funds/index.js` |
| `funds/show.blade.php` | `css/funds/funds.css` + `css/shared/charts.css` | `js/funds/show.js` |
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

> **Note**: The **frontend** (this doc's scope, `layouts/app.blade.php`) uses Bootstrap **4.5.2** — do not use Bootstrap 5 class names or JS APIs here. The separate **admin panel** (`layouts/admin.blade.php`) uses Tabler/Bootstrap 5 via CDN instead; see [docs/RBAC.md](RBAC.md) and [docs/STRUCTURE.md](STRUCTURE.md) for backend views.

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

## Search autocomplete conventions

- Every symbol search box (home, `/stock`, `/stock/compare`) uses `shared/autocomplete.js` — do not instantiate `Awesomplete` directly and do not restyle `.awesomplete` in page CSS (all styling is in `css/shared/autocomplete.css`, loaded globally by the layout).
- **One highlighted row at a time**: keyboard selection and mouse hover are the same state (`aria-selected`), styled in brand blue with a solid ticker chip and a left accent bar. There is intentionally no separate `:hover` colour — two competing highlights was the original UX bug. Hover moves the selection *without* Awesomplete's `goto()` because that call also scrolls the list.
- Rows are built with DOM nodes (`textContent`), never HTML strings; matched text uses a soft `<mark>` tint, not the CDN's neon yellow.
- The endpoint `GET /stocks-list?q=` returns `{symbol,name,exchange}` ranked server-side (exact ticker, prefix, contains, name); the client therefore sets `filter: () => true, sort: false`.

## Admin layout (Tabler) gotchas

- Tabler's `.nav` is a **horizontal** flex row: a collapsible sidebar group must use `.nav-sub` (defined in `layouts/admin.blade.php`), not a bare `nav collapse`.
- Tabler makes `.navbar-nav .nav-link .badge` **`position:absolute`** (notification dot). A text badge inside a nav link needs `position: static !important` (see `.account-meta .badge`).

## Portfolio UI conventions

- One stylesheet, `css/portfolio/portfolio.css` (`pf-*`): buttons are `pf-btn` + one variant (`pf-btn-primary` for THE main action of a page, `-soft` secondary, `-ghost` neutral, `-danger` / `-danger-solid`), `pf-btn-icon` for row actions; a loading button gets `.is-loading` (spinner replaces its icon). Do not add inline `style=` colours or Bootstrap `btn-*` classes to these pages.
- Destructive actions use Bootstrap **modals** (never `confirm()`), and every handler is attached with `addEventListener` in the page's module — an ES module's functions are not global, so inline `onclick="fn()"` silently does nothing (this is why the old "Cập nhật giá" / edit buttons were dead).
- Money is **whole VND** everywhere in the UI and DB; the price feed is thousands of VND — convert only through `App\Support\PriceUnit`.
