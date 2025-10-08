import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface Model {
    id: number;
    created_at: string;
    updated_at: string;
}

export interface User extends Model {
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
}

export interface Transaction extends Model {
    amount: number;
    commission_fee: number;
    sender_id: number;
    receiver_id: number;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface APIResponse<T> {
    data: T;
}

export interface GetTransactionsControllerResponse {
    balance: string;
    meta: PaginationMeta;
    transactions: Transaction[];
}

export interface PaginationMeta {
    pagination: Pagination;
}

export interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
