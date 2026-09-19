// Fund catalog: tick up to N funds, then jump to the comparison page.

const cfg = window.__FUNDS__ || { max: 4, compareUrl: '/funds/compare' };
const bar = document.getElementById('fdCompareBar');
const go = document.getElementById('fdCompareGo');
const boxes = [...document.querySelectorAll('.fd-pick')];

function refresh() {
    const picked = boxes.filter((b) => b.checked).map((b) => b.value);

    // Once the cap is reached, lock the unticked boxes so the limit is obvious.
    boxes.forEach((b) => { b.disabled = !b.checked && picked.length >= cfg.max; });

    bar.hidden = picked.length === 0;
    document.getElementById('fdPickCount').textContent = picked.length;
    document.getElementById('fdPickList').textContent = picked.join(', ') + (picked.length >= cfg.max ? ` (tối đa ${cfg.max})` : '');
    go.href = cfg.compareUrl + '?codes=' + encodeURIComponent(picked.join(','));
}

boxes.forEach((b) => b.addEventListener('change', refresh));
document.getElementById('fdPickClear')?.addEventListener('click', () => {
    boxes.forEach((b) => { b.checked = false; });
    refresh();
});

// Browsers restore ticked boxes on back-navigation; sync the bar with them.
refresh();
