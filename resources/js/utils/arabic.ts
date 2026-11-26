/**
 * Shared Arabic localization, pluralization, and compact display helpers.
 */

export function stripRestaurantPrefix(name: string | null | undefined): string {
    const clean = (name ?? '').trim();
    if (!clean) return 'ت';

    const stripped = clean
        .replace(/^(?:مطعم|مقهى|كافيه|كوفي\s+شوب|كوفي|مخبز|حلويات)\s+/u, '')
        .trim();

    return stripped || clean;
}

export function formatArabicCount(
    count: number,
    singular: string,
    dual: string,
    fewPlural: string,
    singularAccusative: string,
    oneLabel?: string
): string {
    const n = Math.max(0, Math.floor(Number(count) || 0));

    if (n === 1) {
        return oneLabel ?? `1 ${singular}`;
    }
    if (n === 2) {
        return dual;
    }

    const mod100 = n % 100;
    if (n === 0 || (mod100 >= 3 && mod100 <= 10)) {
        return `${n} ${fewPlural}`;
    }
    if (mod100 >= 11) {
        return `${n} ${singularAccusative}`;
    }

    return `${n} ${singular}`;
}

export function formatTableBadgeCode(name: string | null | undefined): string {
    const clean = (name ?? '').trim();
    if (!clean) return '#';

    // Prefer explicit Latin-alphanumeric codes like T1, F1, J1, V1, VIP 1
    const latinCode = clean.match(/\b([A-Za-z]{1,4})\s*[-#]?\s*(\d{1,3})\b/);
    if (latinCode) {
        const prefix = latinCode[1].toUpperCase();
        const digits = latinCode[2];
        return prefix.length >= 3 ? `${prefix} ${digits}` : `${prefix}${digits}`;
    }

    // Standard generic names: "طاولة 1", "Table #2", "12"
    const genericMatch = clean.match(/^(?:(?:طاولة|ترابيزة|table|tbl)\s*)?#?\s*(\d{1,4})$/i);
    if (genericMatch) {
        return `#${genericMatch[1]}`;
    }

    // Descriptive Arabic names with numbers: "طاولة النافورة 1" -> "النافورة 1", "شرفة الياسمين 2" -> "شرفة 2"
    const trailingDigits = clean.match(/^(.*?)\s+(\d{1,4})$/);
    if (trailingDigits) {
        const words = trailingDigits[1]
            .replace(/^(?:طاولة|ترابيزة|ركن|جلسة|جناح)\s+/u, '')
            .trim()
            .split(/\s+/);
        const leadWord = words[0] || '';
        return leadWord ? `${leadWord.slice(0, 8)} ${trailingDigits[2]}` : `#${trailingDigits[2]}`;
    }

    const compact = clean.split(/\s+/).slice(0, 2).join(' ');
    return compact.length > 12 ? `${compact.slice(0, 11)}…` : compact;
}
