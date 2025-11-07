import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { GithubIcon } from 'lucide-react';

type SourceProvider = {
    id: number;
    provider: string;
    username: string;
    email?: string | null;
    created_at: string;
} | null;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Source Provider settings',
        href: '/settings/source-providers',
    },
];

export default function SourceProviders({ githubProvider }: { githubProvider: SourceProvider }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Source Provider settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Source Providers" description="Connect your Git hosting providers to enable auto-deploy" />

                    <div className="space-y-4">
                        {githubProvider ? (
                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-neutral-100 p-2 dark:bg-neutral-800">
                                        <GithubIcon className="size-5" />
                                    </div>
                                    <div>
                                        <div className="font-medium">GitHub</div>
                                        <div className="text-sm text-muted-foreground">Connected as {githubProvider.username}</div>
                                    </div>
                                </div>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        if (confirm('Are you sure you want to disconnect GitHub?')) {
                                            router.delete('/settings/source-providers/github');
                                        }
                                    }}
                                >
                                    Disconnect
                                </Button>
                            </div>
                        ) : (
                            <div className="flex items-center justify-between rounded-lg border border-dashed p-4">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-full bg-neutral-100 p-2 dark:bg-neutral-800">
                                        <GithubIcon className="size-5" />
                                    </div>
                                    <div>
                                        <div className="font-medium">GitHub</div>
                                        <div className="text-sm text-muted-foreground">Connect GitHub to enable auto-deploy for your sites</div>
                                    </div>
                                </div>
                                <Button
                                    onClick={() => {
                                        window.location.href = '/settings/source-providers/github/connect';
                                    }}
                                >
                                    Connect GitHub
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
