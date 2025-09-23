import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface ServerPackageEvent {
    id: number;
    server_id: number;
    service_type: string;
    provision_type: 'install' | 'uninstall';
    milestone: string;
    current_step: number;
    total_steps: number;
    progress_percentage: number;
    details: Record<string, unknown> | null;
    label?: string | null;
    status: 'pending' | 'success' | 'failed';
    error_log?: string | null;
    created_at: string;
    updated_at: string;
}

export interface Server {
    id: number;
    vanity_name: string;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: 'pending' | 'connecting' | 'connected' | 'failed' | 'disconnected';
    server_type?: string | null;
    provision_status: 'pending' | 'connecting' | 'installing' | 'completed' | 'failed';
    provision_status_label: string;
    provision_status_color: string;
    created_at: string;
    updated_at: string;
}
