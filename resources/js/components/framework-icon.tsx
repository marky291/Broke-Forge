import { cn } from '@/lib/utils';
import { Code } from 'lucide-react';

export type Framework = 'laravel' | 'wordpress' | 'generic-php' | 'static-html' | null;

interface FrameworkConfig {
    name: string;
    abbreviation: string;
    svgPath?: string; // Path to SVG file in public directory
    svgBgColor?: string; // Optional background color for SVG icons
    bgColor: string;
    textColor: string;
    darkBgColor: string;
    darkTextColor: string;
}

const frameworkConfigs: Record<string, FrameworkConfig> = {
    laravel: {
        name: 'Laravel',
        abbreviation: 'LRV',
        svgPath: '/framework/laravel.svg',
        bgColor: 'bg-red-100',
        textColor: 'text-red-700',
        darkBgColor: 'dark:bg-red-900/30',
        darkTextColor: 'dark:text-red-400',
    },
    wordpress: {
        name: 'WordPress',
        abbreviation: 'WP',
        svgPath: '/framework/wordpress.svg',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-700',
        darkBgColor: 'dark:bg-blue-900/30',
        darkTextColor: 'dark:text-blue-400',
    },
    'generic-php': {
        name: 'Generic PHP',
        abbreviation: 'PHP',
        svgPath: '/framework/php.svg',
        bgColor: 'bg-purple-100',
        textColor: 'text-purple-700',
        darkBgColor: 'dark:bg-purple-900/30',
        darkTextColor: 'dark:text-purple-400',
    },
    'static-html': {
        name: 'Static HTML',
        abbreviation: 'HTML',
        svgPath: '/framework/html.svg',
        bgColor: 'bg-orange-100',
        textColor: 'text-orange-700',
        darkBgColor: 'dark:bg-orange-900/30',
        darkTextColor: 'dark:text-orange-400',
    },
};

export interface FrameworkIconProps {
    framework: Framework;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

/**
 * FrameworkIcon Component
 *
 * Displays framework logo (SVG) or colored badge for the site's framework.
 *
 * @example
 * ```tsx
 * <FrameworkIcon framework="laravel" size="md" />
 * <FrameworkIcon framework={site.framework} />
 * ```
 */
export function FrameworkIcon({ framework, size = 'md', className }: FrameworkIconProps) {
    // Default to generic code icon if no framework specified
    if (!framework) {
        return <Code className={cn('text-muted-foreground', className)} />;
    }

    const config = frameworkConfigs[framework];

    // Fallback to generic icon if framework not recognized
    if (!config) {
        return <Code className={cn('text-muted-foreground', className)} />;
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

    // If framework has an SVG file, use it
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

        // Wrap in background container if svgBgColor is specified
        if (config.svgBgColor) {
            return (
                <div
                    className={cn('flex items-center justify-center rounded-md p-1', className)}
                    style={{ backgroundColor: config.svgBgColor }}
                    title={config.name}
                >
                    {imgElement}
                </div>
            );
        }

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
 * Get framework configuration for display purposes
 */
export function getFrameworkConfig(framework: Framework): FrameworkConfig | null {
    if (!framework) return null;
    return frameworkConfigs[framework] || null;
}

/**
 * Get all available frameworks for forms/selects
 */
export function getAllFrameworks(): Array<{ value: string; label: string }> {
    return Object.entries(frameworkConfigs).map(([value, config]) => ({
        value,
        label: config.name,
    }));
}
