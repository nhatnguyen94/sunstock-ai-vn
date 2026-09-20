// Pure helpers for the trade modal (no DOM, unit-tested in Node): fee estimate and the live "what will happen" preview.
// Rates mirror what Vietnamese brokers charge: ~0.15% commission both ways, plus 0.1% personal-income tax on a sell.

const nf = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });
const fmt = (v) => nf.format(Math.round(v));

export const BUY_FEE_RATE = 0.0015;
export const SELL_FEE_RATE = 0.0015 + 0.001;

export function estimateFee(type, qty, price) {
    const rate = type === 'sell' ? SELL_FEE_RATE : BUY_FEE_RATE;
    return Math.round(qty * price * rate);
}

/**
 * @returns {{html: string, tone?: string}|null}
 *  buy  -> total cost and the new average cost per share (fee included)
 *  sell -> proceeds after fee and the realised P&L against the average cost
 */
export function tradePreview({ type, qty, price, fee = 0, holding = null }) {
    if (!qty || !price) {
        return holding ? { html: `Đang giữ <b>${fmt(holding.qty)}</b> cp · giá vốn bình quân <b>${fmt(holding.avg)}₫</b>` } : null;
    }

    if (type === 'buy') {
        const cost = qty * price + fee;
        const held = holding ? holding.qty : 0;
        const avgNew = (held * (holding ? holding.avg : 0) + cost) / (held + qty);
        return {
            html: `Tổng chi: <b>${fmt(cost)}₫</b>` + (holding
                ? `<br>Giá vốn bình quân: ${fmt(holding.avg)}₫ → <b>${fmt(avgNew)}₫</b> (giữ ${fmt(held + qty)} cp)`
                : `<br>Giá vốn (đã gồm phí): <b>${fmt(cost / qty)}₫</b>/cp`),
        };
    }

    if (!holding) return { tone: 'warn', html: 'Mã này chưa có trong danh mục nên không thể bán.' };
    if (qty > holding.qty) return { tone: 'warn', html: `Bạn chỉ đang giữ <b>${fmt(holding.qty)}</b> cp, không thể bán ${fmt(qty)}.` };

    const proceeds = qty * price - fee;
    const pnl = proceeds - qty * holding.avg;
    const pct = (pnl / (qty * holding.avg)) * 100;
    const cls = pnl >= 0 ? 'up' : 'down';
    return {
        tone: 'sell',
        html: `Tiền nhận về: <b>${fmt(proceeds)}₫</b> · giá vốn BQ ${fmt(holding.avg)}₫<br>` +
            `Lãi/lỗ chốt: <b class="${cls}">${pnl > 0 ? '+' : ''}${fmt(pnl)}₫ (${pnl > 0 ? '+' : ''}${pct.toFixed(2).replace('.', ',')}%)</b>` +
            (qty === holding.qty ? '<br>Bán hết → mã sẽ rời khỏi danh mục.' : ''),
    };
}
