import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, ChevronsUpDown, Folder, LayoutGrid, Menu, X } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from './app-logo-icon';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';

export function MainHeader() {
    const { url, props } = usePage<SharedData>();
    const { auth } = props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [path = ''] = url.split('?');

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
            isActive: path === dashboard() || path === '/',
        },
    ];

    const resourceNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: Folder,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div className="container mx-auto max-w-7xl px-4">
                <div className="flex h-16 items-center justify-between">
                    {/* Left Section - Logo & Brand */}
                    <div className="flex items-center gap-8">
                        {/* Logo and Brand Name */}
                        <Link
                            href={dashboard()}
                            prefetch
                            className="flex items-center gap-3 transition-opacity hover:opacity-80"
                        >
                            <div className="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
                                <AppLogoIcon className="size-5 fill-current" />
                            </div>
                            <div className="hidden sm:block">
                                <h1 className="text-lg font-semibold">Laravel Starter Kit</h1>
                            </div>
                        </Link>

                        {/* Desktop Navigation - Main Items */}
                        <nav className="hidden md:flex items-center gap-1">
                            <div className="flex items-center gap-1 px-2 py-1 rounded-lg bg-muted/50">
                                <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider mr-2">Platform</span>
                                {mainNavItems.map((item) => {
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            prefetch
                                            className={cn(
                                                'flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
                                                'hover:bg-background/80 hover:shadow-sm',
                                                item.isActive
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {Icon && <Icon className="size-4" />}
                                            <span>{item.title}</span>
                                        </Link>
                                    );
                                })}
                            </div>
                        </nav>
                    </div>

                    {/* Center Section - Resource Links (Desktop) */}
                    <nav className="hidden lg:flex items-center gap-2">
                        {resourceNavItems.map((item) => {
                            const Icon = item.icon;
                            return (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={cn(
                                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-all duration-200',
                                        'text-muted-foreground hover:text-foreground hover:bg-accent',
                                    )}
                                >
                                    {Icon && <Icon className="size-4" />}
                                    <span>{item.title}</span>
                                </a>
                            );
                        })}
                    </nav>

                    {/* Right Section - User Menu & Mobile Toggle */}
                    <div className="flex items-center gap-3">
                        {/* User Menu (Desktop) */}
                        <div className="hidden md:block">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm shadow-sm transition-all hover:bg-accent">
                                        <div className="flex items-center gap-2">
                                            <div className="size-7 rounded-full bg-primary/10 flex items-center justify-center">
                                                <span className="text-xs font-semibold text-primary">
                                                    {auth.user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)}
                                                </span>
                                            </div>
                                            <span className="font-medium">{auth.user.name.split(' ')[0]}</span>
                                        </div>
                                        <ChevronsUpDown className="size-4 text-muted-foreground" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-56" align="end" sideOffset={8}>
                                    <UserMenuContent user={auth.user} />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        {/* Mobile Menu Button */}
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="flex md:hidden items-center justify-center size-10 rounded-lg border bg-card text-muted-foreground shadow-sm transition-all hover:bg-accent hover:text-accent-foreground"
                            aria-label="Toggle navigation menu"
                        >
                            {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile Navigation Dropdown */}
            {mobileMenuOpen && (
                <div className="md:hidden border-t bg-card/50 backdrop-blur">
                    <div className="container mx-auto max-w-7xl px-4 py-6 space-y-6">
                        {/* User Info Section */}
                        <div className="flex items-center gap-3 pb-4 border-b">
                            <div className="size-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <span className="text-sm font-semibold text-primary">
                                    {auth.user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)}
                                </span>
                            </div>
                            <div>
                                <p className="font-medium text-sm">{auth.user.name}</p>
                                <p className="text-xs text-muted-foreground">{auth.user.email}</p>
                            </div>
                        </div>

                        {/* Platform Navigation */}
                        <div>
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-3">Platform</p>
                            <nav className="space-y-1">
                                {mainNavItems.map((item) => {
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            prefetch
                                            onClick={() => setMobileMenuOpen(false)}
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                                                item.isActive
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'hover:bg-accent hover:text-accent-foreground',
                                            )}
                                        >
                                            {Icon && <Icon className="size-4" />}
                                            <span className="font-medium">{item.title}</span>
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>

                        {/* Resources Navigation */}
                        <div>
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-3">Resources</p>
                            <nav className="space-y-1">
                                {resourceNavItems.map((item) => {
                                    const Icon = item.icon;
                                    return (
                                        <a
                                            key={item.href}
                                            href={item.href}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            onClick={() => setMobileMenuOpen(false)}
                                            className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-accent hover:text-accent-foreground transition-colors"
                                        >
                                            {Icon && <Icon className="size-4" />}
                                            <span className="font-medium">{item.title}</span>
                                        </a>
                                    );
                                })}
                            </nav>
                        </div>

                        {/* Account Actions */}
                        <div className="pt-4 border-t">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button className="w-full flex items-center justify-between rounded-lg border bg-background px-3 py-2.5 text-sm hover:bg-accent transition-colors">
                                        <span className="font-medium">Account Settings</span>
                                        <ChevronsUpDown className="size-4 text-muted-foreground" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-[calc(100vw-2rem)]" align="end">
                                    <UserMenuContent user={auth.user} />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}