import { cn } from '@/lib/utils';
import { Database } from 'lucide-react';

export type ServiceType = 'mysql' | 'mariadb' | 'postgresql' | 'redis' | null;

interface ServiceConfig {
    name: string;
    abbreviation: string;
    svgPath?: string;
    bgColor: string;
    textColor: string;
    darkBgColor: string;
    darkTextColor: string;
}

const serviceConfigs: Record<string, ServiceConfig> = {
    mysql: {
        name: 'MySQL',
        abbreviation: 'MY',
        svgPath: '/services/mysql.svg',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-700',
        darkBgColor: 'dark:bg-blue-900/30',
        darkTextColor: 'dark:text-blue-400',
    },
    mariadb: {
        name: 'MariaDB',
        abbreviation: 'MA',
        svgPath: '/services/mariadb.svg',
        bgColor: 'bg-teal-100',
        textColor: 'text-teal-700',
        darkBgColor: 'dark:bg-teal-900/30',
        darkTextColor: 'dark:text-teal-400',
    },
    postgresql: {
        name: 'PostgreSQL',
        abbreviation: 'PG',
        svgPath: '/services/postgresql.svg',
        bgColor: 'bg-indigo-100',
        textColor: 'text-indigo-700',
        darkBgColor: 'dark:bg-indigo-900/30',
        darkTextColor: 'dark:text-indigo-400',
    },
    redis: {
        name: 'Redis',
        abbreviation: 'RD',
        svgPath: '/services/redis.svg',
        bgColor: 'bg-red-100',
        textColor: 'text-red-700',
        darkBgColor: 'dark:bg-red-900/30',
        darkTextColor: 'dark:text-red-400',
    },
};

export interface ServiceIconProps {
    service: ServiceType;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

/**
 * ServiceIcon Component
 *
 * Displays service logo (SVG) or colored badge for database and cache services.
 *
 * @example
 * ```tsx
 * <ServiceIcon service="mysql" size="md" />
 * <ServiceIcon service={database.engine} />
 * ```
 */
export function ServiceIcon({ service, size = 'md', className }: ServiceIconProps) {
    // Default to generic database icon if no service specified
    if (!service) {
        return <Database className={cn('text-muted-foreground', className)} />;
    }

    const config = serviceConfigs[service];

    // Fallback to generic icon if service not recognized
    if (!config) {
        return <Database className={cn('text-muted-foreground', className)} />;
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

    // If service has an SVG file, use it
    if (config.svgPath) {
        const imgElement = (
            <img
                src={config.svgPath}
                alt={config.name}
                title={config.name}
                className={cn('object-contain', sizeClasses[size])}
                width={sizeMap[size]}
                height={sizeMap[size]}
            />
        );

        return <div className={className}>{imgElement}</div>;
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
 * Get service configuration for display purposes
 */
export function getServiceConfig(service: ServiceType): ServiceConfig | null {
    if (!service) return null;
    return serviceConfigs[service] || null;
}

/**
 * Get all available services for forms/selects
 */
export function getAllServices(): Array<{ value: string; label: string }> {
    return Object.entries(serviceConfigs).map(([value, config]) => ({
        value,
        label: config.name,
    }));
}
