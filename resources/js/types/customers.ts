export interface PromoItem {
    id: number;
    code: string;
    source: string;
    discount_label: string;
    discount_value: number;
    discount_type: string;
    starts_at: string | null;
    expires_at: string | null;
    status: 'active' | 'used' | 'expired' | 'inactive' | 'scheduled';
    is_usable: boolean;
    reminded_at: string | null;
    reminders_count: number;
    whatsapp_url: string | null;
    whatsapp_message: string | null;
}

export interface CustomerRow {
    id: number;
    name: string;
    phone: string;
    visits_count: number;
    notes: string | null;
    last_visit_at: string | null;
    segment: 'vip' | 'at_risk' | 'new_lead' | 'regular';
    segment_label: string;
    segment_tagline: string;
    days_since_last_visit: number;
    has_usable_promo: boolean;
    needs_reminder: boolean;
    has_active_loyalty_promo: boolean;
    remaining_visits_for_loyalty: number | null;
    promo_codes: PromoItem[];
}

export type WaConnectionStatus = 'disconnected' | 'connecting' | 'scan_qr' | 'connected';
