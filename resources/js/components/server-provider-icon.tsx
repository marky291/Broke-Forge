import { cn } from '@/lib/utils';
import { Server as ServerIcon } from 'lucide-react';

export type ServerProvider = 'aws' | 'google-cloud' | 'azure' | 'digitalocean' | 'linode' | 'vultr' | 'hetzner' | 'custom' | null;

interface ProviderConfig {
    name: string;
    abbreviation: string;
    svgPath?: string; // Path to SVG file in public directory
    bgColor: string;
    textColor: string;
    darkBgColor: string;
    darkTextColor: string;
}

const providerConfigs: Record<string, ProviderConfig> = {
    aws: {
        name: 'AWS',
        abbreviation: 'AWS',
        svgPath: '/provider/aws.svg',
        bgColor: 'bg-orange-100',
        textColor: 'text-orange-700',
        darkBgColor: 'dark:bg-orange-900/30',
        darkTextColor: 'dark:text-orange-400',
    },
    'google-cloud': {
        name: 'Google Cloud',
        abbreviation: 'GCP',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-700',
        darkBgColor: 'dark:bg-blue-900/30',
        darkTextColor: 'dark:text-blue-400',
    },
    azure: {
        name: 'Azure',
        abbreviation: 'AZ',
        bgColor: 'bg-sky-100',
        textColor: 'text-sky-700',
        darkBgColor: 'dark:bg-sky-900/30',
        darkTextColor: 'dark:text-sky-400',
    },
    digitalocean: {
        name: 'DigitalOcean',
        abbreviation: 'DO',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-700',
        darkBgColor: 'dark:bg-blue-900/30',
        darkTextColor: 'dark:text-blue-400',
    },
    linode: {
        name: 'Linode',
        abbreviation: 'LND',
        bgColor: 'bg-green-100',
        textColor: 'text-green-700',
        darkBgColor: 'dark:bg-green-900/30',
        darkTextColor: 'dark:text-green-400',
    },
    vultr: {
        name: 'Vultr',
        abbreviation: 'VTR',
        bgColor: 'bg-indigo-100',
        textColor: 'text-indigo-700',
        darkBgColor: 'dark:bg-indigo-900/30',
        darkTextColor: 'dark:text-indigo-400',
    },
    hetzner: {
        name: 'Hetzner',
        abbreviation: 'HTZ',
        bgColor: 'bg-red-100',
        textColor: 'text-red-700',
        darkBgColor: 'dark:bg-red-900/30',
        darkTextColor: 'dark:text-red-400',
    },
    custom: {
        name: 'Custom',
        abbreviation: 'CSTM',
        svgPath: '/provider/custom.svg',
        bgColor: 'bg-gray-100',
        textColor: 'text-gray-700',
        darkBgColor: 'dark:bg-gray-900/30',
        darkTextColor: 'dark:text-gray-400',
    },
};

export interface ServerProviderIconProps {
    provider: ServerProvider;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

/**
 * ServerProviderIcon Component
 *
 * Displays provider logo (SVG) or colored badge for the server's cloud provider.
 *
 * @example
 * ```tsx
 * <ServerProviderIcon provider="aws" size="md" />
 * <ServerProviderIcon provider={server.provider} />
 * ```
 */
export function ServerProviderIcon({ provider, size = 'md', className }: ServerProviderIconProps) {
    // Default to generic server icon if no provider specified
    if (!provider) {
        return <ServerIcon className={cn('text-muted-foreground', className)} />;
    }

    const config = providerConfigs[provider];

    // Fallback to generic icon if provider not recognized
    if (!config) {
        return <ServerIcon className={cn('text-muted-foreground', className)} />;
    }

    const sizeMap = {
        sm: 20,
        md: 24,
        lg: 32,
    };

    const sizeClasses = {
        sm: 'size-5 text-xs',
        md: 'size-6 text-sm',
        lg: 'size-8 text-base',
    };

    // If provider has an SVG file, use it
    if (config.svgPath) {
        return (
            <img
                src={config.svgPath}
                alt={config.name}
                title={config.name}
                className={cn('object-contain', sizeClasses[size], className)}
                width={sizeMap[size]}
                height={sizeMap[size]}
            />
        );
    }

    // Otherwise, use colored badge fallback
    return (
        <div
            className={cn(
                'flex items-center justify-center rounded-md font-semibold',
                config.bgColor,
                config.textColor,
                config.darkBgColor,
                config.darkTextColor,
                sizeClasses[size],
                className,
            )}
            title={config.name}
        >
            {config.abbreviation}
        </div>
    );
}

/**
 * Get provider configuration for display purposes
 */
export function getProviderConfig(provider: ServerProvider): ProviderConfig | null {
    if (!provider) return null;
    return providerConfigs[provider] || null;
}

/**
 * Get all available providers for forms/selects
 */
export function getAllProviders(): Array<{ value: string; label: string }> {
    return Object.entries(providerConfigs).map(([value, config]) => ({
        value,
        label: config.name,
    }));
}
