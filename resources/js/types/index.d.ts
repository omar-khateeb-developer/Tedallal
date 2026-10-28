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
        };
    };
    restaurantId?: number;
};
