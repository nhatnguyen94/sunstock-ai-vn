// Bottom navigation on phones: slides away while the reader scrolls down (more room for the data) and comes back as soon
// as they scroll up, reach the top or the end of the page. The decision is a pure function so it is unit-tested in Node.

/**
 * Should the bar be visible after scrolling from `prev` to `y`?
 * @param {{prev:number, y:number, visible:boolean, delta?:number, top?:number, maxY?:number}} s
 */
export function nextVisible({ prev, y, visible, delta = 8, top = 80, maxY = Infinity }) {
    if (y <= top) return true;                        // near the top: always there
    if (Number.isFinite(maxY) && y >= maxY - 4) return true;   // at the very end (footer links are right above it)
    if (Math.abs(y - prev) < delta) return visible;   // a tremor is not a direction
    return y < prev;                                  // up = show, down = hide
}

export function initMobileNav(nav = document.getElementById('mobileNav')) {
    if (!nav) return;
    let prev = window.scrollY;
    let visible = true;
    let queued = false;

    const update = () => {
        queued = false;
        const y = window.scrollY;
        const maxY = document.documentElement.scrollHeight - window.innerHeight;
        const next = nextVisible({ prev, y, visible, maxY });
        if (next !== visible) {
            visible = next;
            nav.classList.toggle('is-hidden', !visible);
        }
        prev = y;
    };

    window.addEventListener('scroll', () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    // a keyboard opening or a focused field must not leave the bar hidden over the form
    document.addEventListener('focusin', (e) => {
        if (e.target.matches?.('input, textarea, select')) nav.classList.remove('is-hidden');
    });
}
