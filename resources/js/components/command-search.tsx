import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Globe, Search, Server, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface SearchItem {
    id: number;
    name: string;
    category: 'Servers' | 'Sites';
    url: string;
    serverId?: number;
    serverName?: string;
    icon: typeof Server | typeof Globe;
}

export function CommandSearch() {
    const { props } = usePage<SharedData & { searchServers?: any[]; searchSites?: any[] }>();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const searchRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const optionsRef = useRef<HTMLDivElement>(null);

    // Build items from servers and sites data
    const allItems: SearchItem[] = [
        // Add servers
        ...(props.searchServers || []).map((server) => ({
            id: server.id,
            name: server.name || `Server #${server.id}`,
            category: 'Servers' as const,
            url: `/servers/${server.id}`,
            icon: Server,
        })),
        // Add sites
        ...(props.searchSites || []).map((site) => ({
            id: site.id,
            name: site.domain || `Site #${site.id}`,
            category: 'Sites' as const,
            url: `/servers/${site.server_id}/sites/${site.id}`,
            serverId: site.server_id,
            serverName: site.server_name,
            icon: Globe,
        })),
    ];

    // Filter items based on query
    const filteredItems =
        query === ''
            ? allItems
            : allItems.filter((item) => {
                  const searchText = `${item.name} ${item.category} ${item.serverName || ''}`.toLowerCase();
                  return searchText.includes(query.toLowerCase());
              });

    // Group items by category
    const groupedItems = filteredItems.reduce(
        (groups, item) => {
            const category = item.category;
            if (!groups[category]) {
                groups[category] = [];
            }
            groups[category].push(item);
            return groups;
        },
        {} as Record<string, SearchItem[]>,
    );

    // Handle navigation
    const handleSelect = (item: SearchItem) => {
        router.visit(item.url);
        setOpen(false);
        setQuery('');
    };

    // Keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((prev) => (prev + 1) % filteredItems.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((prev) => (prev - 1 + filteredItems.length) % filteredItems.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (filteredItems[selectedIndex]) {
                handleSelect(filteredItems[selectedIndex]);
            }
        } else if (e.key === 'Escape') {
            setOpen(false);
            setQuery('');
        }
    };

    // Click outside to close & prevent body scroll
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (searchRef.current && !searchRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
            // Prevent body scroll on mobile
            document.body.style.overflow = 'hidden';
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            // Restore body scroll
            document.body.style.overflow = '';
        };
    }, [open]);

    // Global keyboard shortcut (Cmd/Ctrl + K)
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setOpen(true);
                setTimeout(() => inputRef.current?.focus(), 0);
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    // Reset selected index when query changes
    useEffect(() => {
        setSelectedIndex(0);
    }, [query]);

    // Scroll selected item into view
    useEffect(() => {
        if (open && optionsRef.current && selectedIndex >= 0) {
            const selectedElement = optionsRef.current.querySelector(`[data-index="${selectedIndex}"]`);
            selectedElement?.scrollIntoView({ block: 'nearest' });
        }
    }, [selectedIndex, open]);

    return (
        <div ref={searchRef} className="relative w-full max-w-xl">
            {/* Search Trigger */}
            <button
                onClick={() => {
                    setOpen(true);
                    setTimeout(() => inputRef.current?.focus(), 0);
                }}
                className="group flex h-10 w-full items-center gap-2.5 rounded-lg border border-white/20 bg-white/15 px-3 shadow-lg backdrop-blur-sm transition-all hover:border-white/30 hover:bg-white/25 md:px-4"
            >
                <Search className="size-4 text-white/80 group-hover:text-white" />
                <span className="hidden flex-1 text-left text-sm text-white/80 group-hover:text-white sm:flex">Search servers and sites...</span>
                <kbd className="hidden items-center gap-1 rounded border border-white/30 bg-white/20 px-2 py-0.5 text-[10px] font-medium text-white/90 shadow-sm md:inline-flex">
                    <span className="text-xs">⌘</span>K
                </kbd>
            </button>

            {/* Backdrop */}
            {open && <div className="fixed inset-0 z-40 bg-black/50 md:hidden" onClick={() => setOpen(false)} />}

            {/* Command Palette */}
            {open && (
                <div className="fixed inset-x-4 top-20 z-50 overflow-hidden rounded-lg border bg-card shadow-xl md:absolute md:inset-x-auto md:top-full md:right-0 md:mt-2 md:w-full">
                    {/* Search Input */}
                    <div className="flex items-center border-b px-3">
                        <Search className="size-4 text-muted-foreground" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder="Search servers and sites..."
                            className="flex-1 bg-transparent px-3 py-3 text-sm outline-none placeholder:text-muted-foreground"
                            autoFocus
                        />
                        {query && (
                            <button onClick={() => setQuery('')} className="text-muted-foreground hover:text-foreground">
                                <X className="size-4" />
                            </button>
                        )}
                    </div>

                    {/* Results */}
                    <div ref={optionsRef} className="max-h-[400px] overflow-y-auto">
                        {filteredItems.length === 0 ? (
                            <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                                {query ? 'No results found' : 'Type to search servers and sites'}
                            </div>
                        ) : (
                            <div className="py-2">
                                {Object.entries(groupedItems).map(([category, items]) => (
                                    <div key={category}>
                                        <div className="px-3 py-1.5 text-xs font-semibold text-muted-foreground">{category}</div>
                                        {items.map((item, index) => {
                                            const Icon = item.icon;
                                            const globalIndex = filteredItems.indexOf(item);
                                            return (
                                                <button
                                                    key={`${item.category}-${item.id}`}
                                                    data-index={globalIndex}
                                                    onClick={() => handleSelect(item)}
                                                    onMouseEnter={() => setSelectedIndex(globalIndex)}
                                                    className={cn(
                                                        'flex w-full items-center gap-3 px-3 py-2 text-sm transition-colors',
                                                        globalIndex === selectedIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/50',
                                                    )}
                                                >
                                                    <Icon className="size-4 flex-shrink-0" />
                                                    <div className="flex-1 text-left">
                                                        <div className="font-medium">{item.name}</div>
                                                        {item.serverName && <div className="text-xs text-muted-foreground">on {item.serverName}</div>}
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Footer hint */}
                    <div className="border-t px-3 py-2 text-xs text-muted-foreground">
                        <span>Use ↑↓ to navigate • ↵ to select • esc to close</span>
                    </div>
                </div>
            )}
        </div>
    );
}
