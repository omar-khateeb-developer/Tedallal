export interface TableItem {
    id: number;
    name: string;
    public_token: string;
    qr_url: string;
    public_url?: string;
    status: 'active' | 'inactive';
    table_type?: 'dine_in' | 'takeaway';
    pos_x: number;
    pos_y: number;
    shape: 'circle' | 'rectangle' | 'booth' | 'vip';
    rotation: number;
    capacity: number;
    zone: string;
    width: number;
    height: number;
    iot_device_id?: string | null;
    operational_stage?: 'available' | 'seated' | 'preparing' | 'served' | 'bill_requested' | 'cleaning';
    stage_updated_at?: string;
    session_started_at?: string;
    bill_requested_at?: string;
    current_guest_name?: string;
    turn_minutes?: number;
    stage_minutes?: number;
    bill_wait_minutes?: number | null;
    has_waiter_call?: boolean;
    service_call?: any;
    active_session?: any;
    active_order?: {
        id: number;
        order_number: string;
        status: string;
        total: number;
        items_count: number;
        created_at?: string;
    } | null;
}

export interface WaitlistItem {
    id: number;
    uuid: string;
    guest_name: string;
    guest_phone: string;
    party_size: number;
    seating_preference: string;
    status: 'waiting' | 'notified' | 'seated' | 'cancelled' | 'no_show';
    queue_number: number;
    estimated_wait_minutes: number;
    waiting_minutes: number;
    notified_at?: string;
    assigned_table?: { id: number; name: string } | null;
    notes?: string;
    tracking_url?: string;
}

export interface ReservationItem {
    id: number;
    uuid: string;
    code: string;
    table_id: number;
    guest_name: string;
    guest_phone?: string;
    reservation_date: string;
    time_slot: string;
    guests_count: number;
    special_requests?: string;
    status: string;
    table_name?: string;
    table_zone?: string;
}

export const FLOOR_ZONE_LABELS: Record<string, string> = {
    all: 'كامل الصالة',
    main: 'الصالة الرئيسية',
    vip: 'جناح النخبة (VIP)',
    terrace: 'الشرفة الخارجية',
    family: 'القسم العائلي',
};

export const TABLE_STAGE_LABELS: Record<string, string> = {
    available: 'شاغرة',
    seated: 'تم الجلوس',
    preparing: 'تحضير بالمطبخ',
    served: 'تم التقديم',
    bill_requested: 'طلب الحساب',
    cleaning: 'بانتظار التنظيف',
};

export function getFloorZoneLabel(zoneKey: string): string {
    return FLOOR_ZONE_LABELS[zoneKey] || zoneKey;
}
