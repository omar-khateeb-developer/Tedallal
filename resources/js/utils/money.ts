export function normalizeCurrency(currency?: string | null): string {
    if (!currency || currency.toUpperCase().trim() === 'SYP') {
        return 'ل.س';
    }
    return currency;
}

export function formatAmount(val: string | number | null | undefined): string {
    if (val === null || val === undefined || val === '') {
        return '0';
    }
    const clean = String(val).replace(/,/g, '').trim();
    const num = parseFloat(clean);
    if (isNaN(num)) {
        return '0';
    }
    return Number.isInteger(num)
        ? num.toLocaleString()
        : parseFloat(num.toFixed(2)).toLocaleString();
}

export function formatMoney(val: string | number | null | undefined, currency?: string | null): string {
    return `${formatAmount(val)} ${normalizeCurrency(currency)}`;
}

/**
 * Exact non-negative decimal subtraction using integer minor units (1e-2)
 * so JS UI previews match backend BigDecimal math without IEEE-754 float drift.
 */
export function subtractMoney(minuend: string | number | null | undefined, subtrahend: string | number | null | undefined): string {
    const parseCents = (raw: string | number | null | undefined): number => {
        const clean = String(raw ?? '0').replace(/,/g, '').trim();
        const num = parseFloat(clean);
        return Number.isFinite(num) ? Math.round(num * 100) : 0;
    };

    const diffCents = Math.max(0, parseCents(minuend) - parseCents(subtrahend));
    return (diffCents / 100).toFixed(2);
}
