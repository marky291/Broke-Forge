import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface ServerEvent {
    id: number;
    server_id: number;
    service_type: string;
    provision_type: 'install' | 'uninstall';
    milestone: string;
    current_step: number;
    total_steps: number;
    progress_percentage: number;
    details: Record<string, unknown> | null;
    label?: string | null;
    status: 'pending' | 'success' | 'failed';
    error_log?: string | null;
    created_at: string;
    updated_at: string;
}

export interface ProvisionStep {
    step: number;
    status: 'pending' | 'connecting' | 'installing' | 'completed' | 'failed';
}

export interface Server {
    id: number;
    vanity_name: string;
    provider?: string | null;
    public_ip: string;
    ssh_port: number;
    private_ip?: string | null;
    connection: 'pending' | 'connecting' | 'connected' | 'failed' | 'disconnected';
    provision_status: 'pending' | 'connecting' | 'installing' | 'completed' | 'failed';
    provision?: ProvisionStep[] | null;
    provision_status_label: string;
    provision_status_color: string;
    scheduler_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    scheduler_installed_at?: string | null;
    scheduler_uninstalled_at?: string | null;
    supervisor_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    supervisor_installed_at?: string | null;
    supervisor_uninstalled_at?: string | null;
    monitoring_status?: 'installing' | 'active' | 'failed' | 'uninstalling' | 'uninstalled' | null;
    monitoring_collection_interval?: number | null;
    monitoring_installed_at?: string | null;
    monitoring_uninstalled_at?: string | null;
    isFirewallInstalled?: boolean;
    firewallStatus?: string;
    rules?: ServerFirewallRule[];
    recentEvents?: ServerEvent[];
    latestMetrics?: ServerMetric | null;
    recentMetrics?: ServerMetric[];
    scheduledTasks?: ServerScheduledTask[];
    recentTaskRuns?: {
        data: ServerScheduledTaskRun[];
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            per_page: number;
            to: number | null;
            total: number;
        };
    };
    supervisorTasks?: ServerSupervisorTask[];
    installedDatabase?: {
        id: number;
        service_name: string;
        configuration: {
            type: string;
            version: string;
            root_password?: string;
        };
        status: string;
        progress_step?: number | null;
        progress_total?: number | null;
        progress_label?: string | null;
        installed_at?: string;
    } | null;
    databases?: {
        id: number;
        name: string;
        type: string;
        version: string;
        port: number;
        status: string;
        created_at: string;
    }[];
    phps: ServerPhp[];
    sites: ServerSite[];
    availablePhpVersions: Record<string, string>;
    phpExtensions: Record<string, string>;
    defaultSettings: Record<string, string | number>;
    created_at: string;
    updated_at: string;
}

export interface ServerPhpModule {
    id: number;
    name: string;
    is_enabled: boolean;
}

export interface ServerPhp {
    id: number;
    server_id: number;
    version: string;
    status: string;
    is_cli_default: boolean;
    is_site_default: boolean;
    modules?: ServerPhpModule[];
    created_at?: string;
    updated_at?: string;
}

export interface ServerFirewall {
    id: number;
    server_id: number;
    is_enabled: boolean;
    rules?: ServerFirewallRule[];
    created_at: string;
    updated_at: string;
}

export interface ServerFirewallRule {
    id: number;
    server_firewall_id: number;
    name: string;
    port: string;
    from_ip_address?: string | null;
    rule_type: 'allow' | 'deny';
    status: 'pending' | 'installing' | 'active' | 'failed' | 'removing';
    created_at: string;
    updated_at: string;
}

export interface ServerDatabase {
    id: number;
    server_id: number;
    name: string;
    type: 'mysql' | 'mariadb' | 'postgresql' | 'mongodb' | 'redis';
    version: string;
    port: number;
    status: string;
    root_password?: string | null;
    created_at: string;
    updated_at: string;
}

export interface ServerReverseProxy {
    id: number;
    server_id: number;
    type: 'nginx' | 'apache' | 'caddy';
    version?: string | null;
    worker_processes: string;
    worker_connections: number;
    status: string;
    created_at: string;
    updated_at: string;
}

export interface ServerMetric {
    id: number;
    server_id: number;
    cpu_usage: number;
    memory_total_mb: number;
    memory_used_mb: number;
    memory_usage_percentage: number;
    storage_total_gb: number;
    storage_used_gb: number;
    storage_usage_percentage: number;
    collected_at: string;
    created_at: string;
}

export interface ServerScheduledTask {
    id: number;
    server_id: number;
    name: string;
    command: string;
    frequency: 'minutely' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'custom';
    cron_expression: string | null;
    status: 'active' | 'paused' | 'failed';
    last_run_at: string | null;
    next_run_at: string | null;
    send_notifications: boolean;
    timeout: number;
    created_at: string;
    updated_at: string;
}

export interface ServerScheduledTaskRun {
    id: number;
    server_id: number;
    server_scheduled_task_id: number;
    started_at: string;
    completed_at: string | null;
    exit_code: number | null;
    output: string | null;
    error_output: string | null;
    duration_ms: number | null;
    was_successful: boolean;
    created_at: string;
    updated_at: string;
    task?: ServerScheduledTask;
}

export interface ServerSupervisorTask {
    id: number;
    server_id: number;
    name: string;
    command: string;
    working_directory: string;
    processes: number;
    user: string;
    auto_restart: boolean;
    autorestart_unexpected: boolean;
    status: 'active' | 'inactive' | 'failed';
    stdout_logfile: string | null;
    stderr_logfile: string | null;
    installed_at: string | null;
    uninstalled_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface ServerSite {
    id: number;
    server_id: number;
    domain: string;
    document_root: string;
    php_version: string;
    ssl_enabled: boolean;
    status: string;
    health?: string | null;
    git_status?: string | null;
    git_provider?: string | null;
    git_repository?: string | null;
    git_branch?: string | null;
    last_deployment_sha?: string | null;
    last_deployed_at?: string | null;
    last_deployed_at_human?: string | null;
    auto_deploy_enabled?: boolean;
    error_log?: string | null;
    configuration?: Record<string, unknown> | null;
    provisioned_at?: string | null;
    provisioned_at_human?: string | null;
    git_installed_at?: string | null;
    created_at: string;
    updated_at: string;
    server?: {
        id: number;
        vanity_name: string;
        provider?: string | null;
        public_ip: string;
        private_ip?: string | null;
        connection: string;
        monitoring_status?: string | null;
        latestMetrics?: ServerMetric;
    };
    executionContext?: {
        workingDirectory: string;
        user: string | null;
        timeout: number;
    };
    commandHistory?: {
        data: {
            id: number;
            command: string;
            output: string;
            errorOutput: string;
            exitCode: number | null;
            ranAt: string;
            durationMs: number;
            success: boolean;
        }[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    applicationType?: string | null;
    gitRepository?: {
        provider: string;
        repository: string;
        branch: string;
        deployKey: string;
        lastDeployedSha: string | null;
        lastDeployedAt: string | null;
    } | null;
    deploymentScript?: string | null;
    gitConfig?: {
        provider: string | null;
        repository: string | null;
        branch: string | null;
        deploy_key: string | null;
    };
    deployments?: {
        data: {
            id: number;
            status: 'pending' | 'running' | 'success' | 'failed';
            deployment_script: string;
            output: string | null;
            error_output: string | null;
            exit_code: number | null;
            commit_sha: string | null;
            commit_message: string | null;
            branch: string | null;
            duration_ms: number | null;
            duration_seconds: number | null;
            started_at: string | null;
            completed_at: string | null;
            created_at: string;
            created_at_human: string;
        }[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
    };
    latestDeployment?: {
        id: number;
        status: 'pending' | 'running' | 'success' | 'failed';
        output: string | null;
        error_output: string | null;
        commit_sha: string | null;
        commit_message: string | null;
        branch: string | null;
        duration_seconds: number | null;
        started_at: string | null;
        completed_at: string | null;
    } | null;
}
