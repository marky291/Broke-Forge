/**
 * Git-related constants for the application.
 */

export const GIT_PROVIDERS = [
    { value: 'github', label: 'GitHub' },
    // Add more providers here when supported
] as const;

export type GitProvider = (typeof GIT_PROVIDERS)[number]['value'];

export enum GitStatus {
    NotInstalled = 'not_installed',
    Installing = 'installing',
    Installed = 'installed',
    Failed = 'failed',
    Updating = 'updating',
}

export const GIT_STATUS_LABELS: Record<GitStatus, string> = {
    [GitStatus.NotInstalled]: 'Not Installed',
    [GitStatus.Installing]: 'Installing',
    [GitStatus.Installed]: 'Installed',
    [GitStatus.Failed]: 'Failed',
    [GitStatus.Updating]: 'Updating',
};

export const POLLING_INTERVAL = 3000; // 3 seconds
export const COPY_FEEDBACK_DURATION = 1400; // 1.4 seconds

export const DEFAULT_BRANCH = 'main';
export const DEFAULT_PROVIDER: GitProvider = 'github';

export const DEPLOY_KEY_PLACEHOLDER = 'ssh-ed25519 <deploy-key-will-populate-here-once-generated>';
