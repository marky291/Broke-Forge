import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Menu, Server, Settings, X } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from './app-logo-icon';
import { CommandSearch } from './command-search';

export function MainHeader() {
    const { url, props } = usePage<SharedData>();
    const { auth } = props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [path = ''] = url.split('?');

    const mainNavItems: NavItem[] = [
        {
            title: 'Servers',
            href: dashboard(),
            icon: Server,
            isActive: path === dashboard() || path === '/',
        },
        {
            title: 'Settings',
            href: edit(),
            icon: Settings,
            isActive: path === edit(),
        },
    ];

    return (
        <header
            className="sticky top-0 z-50 w-full border-b"
            style={{
                background: '#151715',
            }}
        >
            <div className="container mx-auto max-w-7xl px-4">
                <div className="flex h-16 items-center gap-3">
                    {/* Logo */}
                    <Link href={dashboard()} prefetch className="flex items-center gap-3 transition-opacity hover:opacity-80">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-white shadow-md">
                            <AppLogoIcon className="size-5 fill-current text-primary" />
                        </div>
                        <div className="hidden lg:block">
                            <h1 className="text-lg font-semibold text-white">Forge</h1>
                        </div>
                    </Link>

                    {/* Desktop Navigation - Main Items */}
                    <nav className="hidden items-center md:flex">
                        {mainNavItems
                            .filter((item) => item.title !== 'Settings')
                            .map((item) => {
                                const Icon = item.icon;
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        prefetch
                                        className={cn(
                                            'flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
                                            'hover:bg-white/20 hover:shadow-sm',
                                            item.isActive ? 'bg-white text-gray-900 shadow-sm' : 'text-white/90 hover:text-white',
                                        )}
                                    >
                                        {Icon && <Icon className="size-4" />}
                                        <span>{item.title}</span>
                                    </Link>
                                );
                            })}
                    </nav>

                    {/* Search - Full width on mobile, centered on desktop */}
                    <div className="flex flex-1 justify-center md:px-8">
                        <CommandSearch />
                    </div>

                    {/* Right Section - User Menu (Desktop) & Mobile Menu Button */}
                    <div className="flex items-center gap-3">
                        {/* User Menu (Desktop) */}
                        <div className="hidden md:block">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex h-10 items-center gap-2 rounded-lg border border-white/20 bg-white/15 px-3 text-sm shadow-lg backdrop-blur-sm transition-all hover:border-white/30 hover:bg-white/25">
                                        <div className="flex items-center gap-2">
                                            <div className="flex size-7 items-center justify-center rounded-full bg-white/20">
                                                <span className="text-xs font-semibold text-white">
                                                    {auth.user.name
                                                        .split(' ')
                                                        .map((n) => n[0])
                                                        .join('')
                                                        .toUpperCase()
                                                        .slice(0, 2)}
                                                </span>
                                            </div>
                                            <span className="font-medium text-white">{auth.user.name.split(' ')[0]}</span>
                                        </div>
                                        <ChevronsUpDown className="size-4 text-white/70" />
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
                            className="flex size-10 flex-shrink-0 items-center justify-center rounded-lg border border-white/20 bg-white/15 text-white shadow-lg backdrop-blur-sm transition-all hover:border-white/30 hover:bg-white/25 md:hidden"
                            aria-label="Toggle navigation menu"
                        >
                            {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile Navigation Dropdown */}
            {mobileMenuOpen && (
                <div className="border-t bg-card/50 backdrop-blur md:hidden">
                    <div className="container mx-auto max-w-7xl px-4 py-4">
                        {/* Navigation */}
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
                                            item.isActive ? 'bg-primary text-primary-foreground' : 'hover:bg-accent hover:text-accent-foreground',
                                        )}
                                    >
                                        {Icon && <Icon className="size-4" />}
                                        <span className="font-medium">{item.title}</span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                </div>
            )}
        </header>
    );
}
