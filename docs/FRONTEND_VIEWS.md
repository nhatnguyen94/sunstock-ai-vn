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
│   │   │   ├── login.css        ← Login page (87 lines)
│   │   │   └── register.css     ← Register page (37 lines)
│   │   ├── exchange_rate/
│   │   │   └── index.css        ← Exchange rate page (799 lines)
│   │   ├── stock/
│   │   │   ├── stock.css        ← Stock detail page (631 lines)
│   │   │   └── compare.css      ← Stock compare page (156 lines)
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
│       ├── exchange_rate/
│       │   └── index.js         ← Exchange rate JS + ApexCharts bar chart (350 lines)
│       ├── stock/
│       │   ├── stock.js         ← Stock chart JS: ApexCharts candlestick + area (239 lines)
│       │   └── compare.js       ← Stock compare JS: ApexCharts multi-line (226 lines)
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
| `auth/verify-email.blade.php` | *(none)* | `js/auth/verify-email.js` |
| `exchange_rate/index.blade.php` | `css/exchange_rate/index.css` | `js/exchange_rate/index.js` |
| `stock/stock.blade.php` | `css/stock/stock.css` | `js/stock/stock.js` |
| `stock/compare.blade.php` | `css/stock/compare.css` | `js/stock/compare.js` |
| `portfolio/index.blade.php` | `css/portfolio/index.css` | *(none)* |
| `portfolio/create.blade.php` | `css/portfolio/create.css` | *(none)* |
| `portfolio/edit.blade.php` | `css/portfolio/edit.css` | *(none)* |
| `portfolio/show.blade.php` | `css/portfolio/show.css` | `js/portfolio/show.js` |
| `portfolio/add-stock.blade.php` | `css/portfolio/add-stock.css` | `js/portfolio/add-stock.js` |
| `profile/show.blade.php` | `css/profile/show.css` | *(none)* |

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

All CSS and JS files under `resources/frontend/` are registered as Vite entry points in `vite.config.js`. When adding a new page asset:

1. Create `resources/frontend/css/[page].css` and/or `resources/frontend/js/[page].js`
2. Add both paths to the `input` array in `vite.config.js`
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
| ApexCharts | 3.49.0 | Charts (stock, compare, exchange rate) |
| AOS | 2.3.4 | Animate on scroll |
| NProgress | 0.2.0 | Page loading bar |
| Awesomplete | 1.1.5 | Symbol autocomplete (stock pages) |
| Inter | Google Font | Typography |

> **Note**: The project uses Bootstrap **4.5.2**, not Bootstrap 5. Do not use Bootstrap 5 class names or JS APIs.

---

## Build Command

```bash
# Recommended (avoids PowerShell execution policy issues on Windows)
node node_modules/vite/bin/vite.js build

# Alternative (if npm ps1 execution policy is enabled)
npm run build
```
