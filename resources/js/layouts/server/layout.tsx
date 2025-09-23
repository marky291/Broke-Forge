import {
    Breadcrumb,
    BreadcrumbItem as BreadcrumbComponent,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AppWindow, CodeIcon, DatabaseIcon, Folder, Globe, Menu, Server, Terminal, X } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

interface ServerLayoutProps extends PropsWithChildren {
    server: {
        id: number;
        vanity_name: string;
        connection: string;
    };
    breadcrumbs?: BreadcrumbItem[];
    site?: {
        id: number;
        domain?: string | null;
    };
}

/**
 * Layout for server-scoped routes that surfaces grouped navigation for server and site contexts.
 */
export default function ServerLayout({ children, server, breadcrumbs, site }: ServerLayoutProps) {
    const { url } = usePage();
    const [path = ''] = url.split('?');
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    // Check current section
    let currentSection: string = 'overview';

    if (path.includes('/explorer')) {
        currentSection = 'explorer';
    } else if (path.includes('/sites/') && path.includes('/commands')) {
        currentSection = 'site-commands';
    } else if (path.includes('/sites/') && site) {
        currentSection = 'site-application';
    } else if (path.includes('/database')) {
        currentSection = 'database';
    } else if (path.includes('/php')) {
        currentSection = 'php';
    } else if (path.includes('/sites')) {
        currentSection = 'sites';
    } else if (path.includes('/settings')) {
        currentSection = 'settings';
    }

    /**
     * Build navigation groups so users can quickly distinguish server-level tools from site-level actions.
     */
    const serverNavItems: NavItem[] = [
        {
            title: 'Sites',
            href: `/servers/${server.id}/sites`,
            icon: Globe,
            isActive: currentSection === 'sites',
        },
        {
            title: 'PHP',
            href: `/servers/${server.id}/php`,
            icon: CodeIcon,
            isActive: currentSection === 'php',
        },
        {
            title: 'Database',
            href: `/servers/${server.id}/database`,
            icon: DatabaseIcon,
            isActive: currentSection === 'database',
        },
        {
            title: 'Explorer',
            href: `/servers/${server.id}/explorer`,
            icon: Folder,
            isActive: currentSection === 'explorer',
        },
        {
            title: 'Settings',
            href: `/servers/${server.id}/settings`,
            icon: Server,
            isActive: currentSection === 'settings',
        },
    ];

    const siteNavItems: NavItem[] = site
        ? [
              {
                  title: 'Application',
                  href: `/servers/${server.id}/sites/${site.id}`,
                  icon: AppWindow,
                  isActive: currentSection === 'site-application',
              },
              {
                  title: 'Commands',
                  href: `/servers/${server.id}/sites/${site.id}/commands`,
                  icon: Terminal,
                  isActive: currentSection === 'site-commands',
              },
          ]
        : [];

    const sidebarNavGroups: NavGroup[] = [
        ...(siteNavItems.length
            ? [
                  {
                      title: site?.domain ? `Site · ${site.domain}` : 'Current Site',
                      items: siteNavItems,
                  },
              ]
            : []),
        {
            title: 'Server',
            items: serverNavItems,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex h-full flex-1 flex-col overflow-x-auto">
                {/* Header Navigation Bar */}
                <header className="w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="px-4 lg:px-8">
                        <div className="flex h-16 items-center justify-between">
                            {/* Server Info Section */}
                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                        <Server className="size-5" />
                                    </div>
                                    <div className="hidden sm:block">
                                        <div className="flex flex-col">
                                            <span className="font-semibold text-sm">{server.vanity_name}</span>
                                            <span className="text-xs text-muted-foreground">Server #{server.id}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Vertical Divider */}
                                <div className="hidden lg:block h-8 w-px bg-border" />
                            </div>

                            {/* Desktop Navigation */}
                            <nav className="hidden md:flex items-center gap-1 lg:gap-2">
                                {/* Site Navigation (if applicable) */}
                                {siteNavItems.length > 0 && (
                                    <>
                                        <div className="flex items-center rounded-lg bg-accent/50 px-2 py-1">
                                            <span className="hidden lg:inline text-xs font-medium text-muted-foreground mr-2 uppercase">
                                                {site?.domain ? `Site: ${site.domain}` : 'Site'}
                                            </span>
                                            {siteNavItems.map((item, index) => {
                                                const Icon = item.icon;
                                                return (
                                                    <Link
                                                        key={`${item.href}-${index}`}
                                                        href={item.href}
                                                        className={cn(
                                                            'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-all',
                                                            'hover:bg-accent hover:text-accent-foreground',
                                                            item.isActive
                                                                ? 'bg-background text-foreground shadow-sm'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {Icon && <Icon className="size-4" />}
                                                        <span>{item.title}</span>
                                                    </Link>
                                                );
                                            })}
                                        </div>

                                        {/* Divider between site and server nav */}
                                        <div className="mx-2 h-8 w-px bg-border" />
                                    </>
                                )}

                                {/* Server Navigation */}
                                <div className="flex items-center gap-1">
                                    <span className="hidden lg:inline text-xs font-medium text-muted-foreground mr-2 uppercase">Server</span>
                                    {serverNavItems.map((item, index) => {
                                        const Icon = item.icon;
                                        return (
                                            <Link
                                                key={`${item.href}-${index}`}
                                                href={item.href}
                                                className={cn(
                                                    'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-all',
                                                    'hover:bg-accent hover:text-accent-foreground',
                                                    item.isActive
                                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {Icon && <Icon className="size-4" />}
                                                <span>{item.title}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </nav>

                            {/* Mobile Menu Toggle */}
                            <button
                                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                                className="flex md:hidden items-center justify-center rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                                aria-label="Toggle navigation menu"
                            >
                                {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                            </button>

                            {/* Right Section - Connection Status */}
                            <div className="flex items-center gap-4">
                                <div className="hidden lg:flex items-center gap-2 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-1.5">
                                        <div className="size-2 rounded-full bg-green-500 animate-pulse" />
                                        <span className="text-xs">{server.connection || 'Connected'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Mobile Navigation Menu */}
                {mobileMenuOpen && (
                    <div className="md:hidden border-b bg-background/95 backdrop-blur">
                        <nav className="px-4 py-4 space-y-4">
                            {/* Site Navigation for Mobile */}
                            {siteNavItems.length > 0 && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase mb-2">
                                        {site?.domain ? `Site: ${site.domain}` : 'Current Site'}
                                    </p>
                                    <div className="space-y-1">
                                        {siteNavItems.map((item, index) => {
                                            const Icon = item.icon;
                                            return (
                                                <Link
                                                    key={`mobile-site-${item.href}-${index}`}
                                                    href={item.href}
                                                    onClick={() => setMobileMenuOpen(false)}
                                                    className={cn(
                                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                                                        item.isActive
                                                            ? 'bg-accent text-accent-foreground'
                                                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                                    )}
                                                >
                                                    {Icon && <Icon className="size-4" />}
                                                    {item.title}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Server Navigation for Mobile */}
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase mb-2">Server</p>
                                <div className="space-y-1">
                                    {serverNavItems.map((item, index) => {
                                        const Icon = item.icon;
                                        return (
                                            <Link
                                                key={`mobile-server-${item.href}-${index}`}
                                                href={item.href}
                                                onClick={() => setMobileMenuOpen(false)}
                                                className={cn(
                                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                                                    item.isActive
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                                )}
                                            >
                                                {Icon && <Icon className="size-4" />}
                                                {item.title}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Connection Status for Mobile */}
                            <div className="pt-2 border-t">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <div className="size-2 rounded-full bg-green-500 animate-pulse" />
                                    <span className="text-xs">{server.connection || 'Connected'}</span>
                                </div>
                            </div>
                        </nav>
                    </div>
                )}

                {/* Main Content Area */}
                <div className="flex-1 p-4 lg:p-6">
                    <div className="mx-auto max-w-7xl">
                        {/* Breadcrumbs */}
                        {breadcrumbs && breadcrumbs.length > 0 && (
                            <div className="mb-4">
                            <Breadcrumb>
                                <BreadcrumbList>
                                    {breadcrumbs.map((breadcrumb, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            {index > 0 && <BreadcrumbSeparator />}
                                            <BreadcrumbComponent>
                                                {index === breadcrumbs.length - 1 ? (
                                                    <BreadcrumbPage>{breadcrumb.title}</BreadcrumbPage>
                                                ) : (
                                                    <BreadcrumbLink href={breadcrumb.href}>{breadcrumb.title}</BreadcrumbLink>
                                                )}
                                            </BreadcrumbComponent>
                                        </div>
                                    ))}
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                    )}

                        {/* Page Content */}
                        <div className="w-full">{children}</div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
