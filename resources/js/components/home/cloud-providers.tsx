import { cn } from '@/lib/utils';
import { Server } from 'lucide-react';

const providers = [
    {
        name: 'AWS',
        bgColor: 'bg-orange-100 dark:bg-orange-900/30',
        textColor: 'text-orange-700 dark:text-orange-400',
    },
    {
        name: 'DigitalOcean',
        bgColor: 'bg-blue-100 dark:bg-blue-900/30',
        textColor: 'text-blue-700 dark:text-blue-400',
    },
    {
        name: 'Vultr',
        bgColor: 'bg-indigo-100 dark:bg-indigo-900/30',
        textColor: 'text-indigo-700 dark:text-indigo-400',
    },
    {
        name: 'Hetzner',
        bgColor: 'bg-red-100 dark:bg-red-900/30',
        textColor: 'text-red-700 dark:text-red-400',
    },
    {
        name: 'Linode',
        bgColor: 'bg-green-100 dark:bg-green-900/30',
        textColor: 'text-green-700 dark:text-green-400',
    },
    {
        name: 'Google Cloud',
        bgColor: 'bg-blue-100 dark:bg-blue-900/30',
        textColor: 'text-blue-700 dark:text-blue-400',
    },
    {
        name: 'Azure',
        bgColor: 'bg-sky-100 dark:bg-sky-900/30',
        textColor: 'text-sky-700 dark:text-sky-400',
    },
    {
        name: 'Custom',
        bgColor: 'bg-gray-100 dark:bg-gray-900/30',
        textColor: 'text-gray-700 dark:text-gray-400',
        isCustom: true,
    },
];

export default function CloudProvidersSection() {
    return (
        <section className="border-t border-white/5 bg-muted/30 py-16">
            <div className="mx-auto max-w-6xl px-6">
                {/* Section Header */}
                <div className="text-center">
                    <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">Deploy to your favorite cloud</h2>
                    <p className="mt-3 text-muted-foreground">Connect any major cloud provider or bring your own server. No vendor lock-in.</p>
                </div>

                {/* Provider Logos */}
                <div className="mt-12 flex flex-wrap items-center justify-center gap-4">
                    {providers.map((provider) => (
                        <div
                            key={provider.name}
                            className={cn(
                                'flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all hover:scale-105',
                                provider.bgColor,
                                provider.textColor,
                            )}
                        >
                            {provider.isCustom ? <Server className="size-4" /> : null}
                            {provider.name}
                        </div>
                    ))}
                </div>

                {/* Additional Info */}
                <p className="mt-8 text-center text-sm text-muted-foreground">
                    Provision servers with a single command. Automatic SSH key management, firewall configuration, and more.
                </p>
            </div>
        </section>
    );
}
