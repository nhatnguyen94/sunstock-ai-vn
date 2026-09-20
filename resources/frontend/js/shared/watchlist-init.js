// Page bootstrap for pages that only need the ★ buttons (stock page, company page).
// The view sets window.__WATCH__ = { auth, watched: [symbols] } before this module runs.

import { initWatchlistStars } from './watchlist.js';

const cfg = window.__WATCH__ || {};
initWatchlistStars({ auth: !!cfg.auth, watched: cfg.watched || [] });
