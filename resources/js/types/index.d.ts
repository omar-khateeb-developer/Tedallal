export interface User {
    id: number;
    name: string;
    email: string;
    role?: string;
    role_label?: string;
    default_window?: number | null;
    status?: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        restaurant?: {
            id: number;
            name: string;
            type?: 'prepaid' | 'postpaid' | string;
            type_label?: string;
            windows_count?: number;
            currency: string;
            logo: string | null;
            navbar_image: string | null;
            plan?: string;
            plan_label?: string;
            features?: Record<string, any>;
            subscription_expires_at?: string | null;
            days_remaining?: number | null;
            is_expired?: boolean;
            whatsapp_status?: string;
            whatsapp_phone?: string | null;
            whatsapp_otp_alert?: {
                message: string;
                phone_masked?: string;
                at?: string;
            } | null;
        };
    };
    restaurantId?: number;
};
