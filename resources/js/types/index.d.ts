export interface User {
    id: number;
    name: string;
    email: string;
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
            logo: string | null;
            navbar_image: string | null;
        };
    };
    restaurantId?: number;
};
