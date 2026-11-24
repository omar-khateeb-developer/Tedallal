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
