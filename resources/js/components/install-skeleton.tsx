import { Button } from '@/components/ui/button';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface InstallSkeletonProps {
    /** Icon component to display (e.g., Activity, Database, Server) */
    icon: LucideIcon;
    /** Title of the installation section */
    title: string;
    /** Description of what will be installed */
    description: string;
    /** Button label text */
    buttonLabel: string;
    /** onClick handler for the install button */
    onInstall: () => void;
    /** Whether the installation is in progress */
    isInstalling?: boolean;
    /** Custom loading text (defaults to "Installing...") */
    installingLabel?: string;
    /** Whether the button should be disabled */
    disabled?: boolean;
}

/**
 * InstallSkeleton Component
 *
 * A reusable empty state component for pages where installation is required.
 * Provides consistent styling and messaging across different installation flows.
 *
 * @example
 * ```tsx
 * import { Activity } from 'lucide-react';
 *
 * <InstallSkeleton
 *   icon={Activity}
 *   title="Monitoring Not Installed"
 *   description="Install monitoring to track CPU, memory, and storage usage on your server."
 *   buttonLabel="Install Monitoring"
 *   onInstall={handleInstall}
 *   isInstalling={processing}
 * />
 * ```
 */
export function InstallSkeleton({
    icon: Icon,
    title,
    description,
    buttonLabel,
    onInstall,
    isInstalling = false,
    installingLabel = 'Installing...',
    disabled = false,
}: InstallSkeletonProps) {
    return (
        <div className="p-8 text-center">
            <Icon className="mx-auto size-12 text-muted-foreground/50" />
            <h3 className="mt-4 text-lg font-semibold">{title}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{description}</p>
            <Button onClick={onInstall} disabled={isInstalling || disabled} className="mt-4">
                {isInstalling ? installingLabel : buttonLabel}
            </Button>
        </div>
    );
}