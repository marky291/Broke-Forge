import { formatRelativeTime } from '@/lib/utils';
import { cn } from '@/lib/utils';
import { GitBranch, Globe, Menu } from 'lucide-react';

interface SiteDetailProps {
    server: {
        public_ip?: string;
        private_ip?: string;
    };
    site: {
        domain: string;
        health?: string | null;
        git_status?: string | null;
        git_repository?: string | null;
        git_branch?: string | null;
        last_deployed_at?: string | null;
    };
    onMobileMenuClick?: () => void;
}

export function SiteDetail({ server, site, onMobileMenuClick }: SiteDetailProps) {
    // Site health indicator
    const getHealthConfig = (health?: string | null) => {
        switch (health) {
            case 'healthy':
                return { color: 'text-green-600', label: 'Healthy' };
            case 'unhealthy':
                return { color: 'text-red-600', label: 'Unhealthy' };
            default:
                return { color: 'text-gray-600', label: 'Unknown' };
        }
    };

    const healthConfig = getHealthConfig(site.health);

    return (
        <div className="w-full border-b bg-card py-4">
            <div className="container mx-auto max-w-7xl px-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-8">
                        {/* Title with Mobile Menu Button */}
                        <div className="flex items-center gap-3">
                            {/* Mobile Menu Button */}
                            {onMobileMenuClick && (
                                <button
                                    onClick={onMobileMenuClick}
                                    className="flex items-center justify-center rounded-md p-2 hover:bg-muted lg:hidden"
                                >
                                    <Menu className="h-5 w-5" />
                                </button>
                            )}

                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                                <Globe className="h-4 w-4 text-primary" />
                            </div>
                            <h1 className="text-xl font-semibold text-foreground">{site.domain}</h1>
                        </div>

                        {/* Server Info - Hide some items on mobile */}
                        <div className="flex flex-wrap items-center gap-4 text-sm lg:gap-8 lg:border-l lg:pl-8">
                            <div>
                                <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Public IP</div>
                                <div className="font-medium">{server.public_ip || 'N/A'}</div>
                            </div>
                            <div className="hidden md:block">
                                <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Private IP</div>
                                <div className="font-medium">{server.private_ip || 'N/A'}</div>
                            </div>
                            <div className="hidden md:block">
                                <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Region</div>
                                <div className="font-medium">Frankfurt</div>
                            </div>
                            <div className="hidden lg:block">
                                <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">OS</div>
                                <div className="font-medium">Ubuntu 24.04</div>
                            </div>
                        </div>

                        {/* Git Info - Responsive */}
                        {site.git_status === 'installed' && site.git_repository && (
                            <div className="flex flex-wrap items-center gap-4 text-sm lg:gap-8 lg:border-l lg:pl-8">
                                <div>
                                    <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Repository</div>
                                    <div className="font-medium">{site.git_repository}</div>
                                </div>
                                {site.git_branch && (
                                    <div>
                                        <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Branch</div>
                                        <div className="flex items-center gap-1.5 font-medium">
                                            <GitBranch className="h-3.5 w-3.5 text-muted-foreground" />
                                            {site.git_branch}
                                        </div>
                                    </div>
                                )}
                                <div className="hidden md:block">
                                    <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Last Deployment</div>
                                    <div className="font-medium">{site.last_deployed_at ? formatRelativeTime(site.last_deployed_at) : 'Never'}</div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Health Indicator */}
                    <div>
                        <div className="mb-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">Health</div>
                        <div className={cn('font-medium', healthConfig.color)}>{healthConfig.label}</div>
                    </div>
                </div>
            </div>
        </div>
    );
}
