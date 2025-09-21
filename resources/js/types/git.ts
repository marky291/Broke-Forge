import type { GitProvider, GitStatus } from '@/constants/git';

export interface ServerSummary {
    id: number;
    vanity_name: string;
    connection: string;
}

export interface SiteSummary {
    id: number;
    domain: string;
    status: string;
    git_status?: GitStatus | null;
    git_installed_at?: string | null;
}

export interface GitRepositoryConfig {
    provider: GitProvider | null;
    repository: string | null;
    branch: string | null;
    deployKey: string | null;
    lastDeployedSha?: string | null;
    lastDeployedAt?: string | null;
}

export interface GitFormData {
    provider: GitProvider;
    repository: string;
    branch: string;
}

export interface SiteGitRepositoryProps {
    server: ServerSummary;
    site: SiteSummary;
    gitRepository?: Partial<GitRepositoryConfig>;
    flash?: {
        success?: string;
        error?: string;
        info?: string;
    };
    errors?: {
        repository?: string;
        branch?: string;
    };
}