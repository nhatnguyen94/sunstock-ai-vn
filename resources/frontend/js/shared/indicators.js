// Technical-indicator maths. Pure functions: number[] in, (number|null)[] out (null = not enough history yet).
// Kept free of DOM/chart imports so they can be unit-tested in Node.

export function sma(values, n) {
    const out = new Array(values.length).fill(null);
    let sum = 0;
    for (let i = 0; i < values.length; i++) {
        sum += values[i];
        if (i >= n) sum -= values[i - n];
        if (i >= n - 1) out[i] = sum / n;
    }
    return out;
}

export function bollinger(values, n = 20, mult = 2) {
    const mid = sma(values, n);
    const upper = new Array(values.length).fill(null);
    const lower = new Array(values.length).fill(null);
    for (let i = n - 1; i < values.length; i++) {
        let sq = 0;
        for (let j = i - n + 1; j <= i; j++) sq += (values[j] - mid[i]) ** 2;
        const sd = Math.sqrt(sq / n);
        upper[i] = mid[i] + mult * sd;
        lower[i] = mid[i] - mult * sd;
    }
    return { upper, mid, lower };
}

export function ema(values, n) {
    const k = 2 / (n + 1);
    const out = new Array(values.length).fill(null);
    const first = values.findIndex((v) => v !== null);
    if (first < 0) return out;
    out[first] = values[first];
    for (let i = first + 1; i < values.length; i++) {
        const prev = out[i - 1];
        out[i] = values[i] !== null ? values[i] * k + prev * (1 - k) : prev;
    }
    return out;
}

export function rsi(values, n = 14) {
    const out = new Array(values.length).fill(null);
    if (values.length < n + 1) return out;
    let gain = 0, loss = 0;
    for (let i = 1; i <= n; i++) {
        const d = values[i] - values[i - 1];
        if (d > 0) gain += d; else loss -= d;
    }
    gain /= n; loss /= n;
    out[n] = 100 - 100 / (1 + gain / (loss || 1e-10));
    for (let i = n + 1; i < values.length; i++) {
        const d = values[i] - values[i - 1];
        gain = (gain * (n - 1) + Math.max(0, d)) / n;
        loss = (loss * (n - 1) + Math.max(0, -d)) / n;
        out[i] = 100 - 100 / (1 + gain / (loss || 1e-10));
    }
    return out;
}

export function macd(values, fast = 12, slow = 26, signal = 9) {
    const f = ema(values, fast), s = ema(values, slow);
    const line = values.map((_, i) => (f[i] !== null && s[i] !== null ? f[i] - s[i] : null));
    const sig = ema(line, signal);
    const hist = line.map((v, i) => (v !== null && sig[i] !== null ? v - sig[i] : null));
    return { line, signal: sig, hist };
}
