export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
}

export type BreadcrumbItem = {
    title: string;
    href: string;
};
