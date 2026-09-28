export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    locale: string;
    default_site?: {
        id: number;
        name: string;
    };
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        roles: string[];
        permissions: string[];
    };
    flash: {
        success?: string;
        warning?: string;
        error?: string;
    };
};
