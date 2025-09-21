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
import { AppWindow, CodeIcon, DatabaseIcon, Folder, Globe, Server, Terminal } from 'lucide-react';
import { PropsWithChildren } from 'react';

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
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex h-full">
                    {/* Server Sidebar */}
                    <div className="relative h-svh w-[16rem] bg-transparent transition-[width] duration-200 ease-linear group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)+(--spacing(4)))] group-data-[collapsible=offcanvas]:w-0 group-data-[side=right]:rotate-180">
                        <div className="flex h-full w-full flex-col rounded-lg border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-sm">
                            {/* Server Header */}
                            <div className="flex items-center gap-3 border-b border-sidebar-border p-4">
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                    <Server className="size-4" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">{server.vanity_name}</span>
                                    <span className="truncate text-xs text-muted-foreground">Server #{server.id}</span>
                                </div>
                            </div>

                            {/* Navigation */}
                            <div className="flex-1 p-2">
                                <nav className="space-y-4">
                                    {sidebarNavGroups.map((group) => (
                                        <div key={group.title} className="space-y-2">
                                            <p className="px-3 text-xs font-medium text-muted-foreground uppercase">{group.title}</p>
                                            <div className="space-y-1">
                                                {group.items.map((item, index) => {
                                                    const Icon = item.icon;
                                                    return (
                                                        <Link
                                                            key={`${item.href}-${index}`}
                                                            href={item.href}
                                                            className={cn(
                                                                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                                                                item.isActive
                                                                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                                                    : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                                            )}
                                                        >
                                                            {Icon && <Icon className="size-4" />}
                                                            {item.title}
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </nav>
                            </div>
                        </div>
                    </div>

                    {/* Main Content Area */}
                    <div className="ml-4 flex min-w-0 flex-1 flex-col">
                        {/* Breadcrumbs Header */}
                        {breadcrumbs && (
                            <header className="flex h-16 shrink-0 items-center gap-2">
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
                            </header>
                        )}

                        {/* Page Content */}
                        <div className="flex-1 overflow-auto">{children}</div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
