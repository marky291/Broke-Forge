import { Activity, Clock, Database, GitBranch, Lock, Shield } from 'lucide-react';

const features = [
    {
        title: 'Git Deployments',
        description: 'Connect GitHub, GitLab, or Bitbucket for seamless auto-deployments. Push to deploy with rollback support.',
        icon: GitBranch,
    },
    {
        title: 'Database Management',
        description: 'MySQL, PostgreSQL, MariaDB, MongoDB, and Redis. Full user and schema management included.',
        icon: Database,
    },
    {
        title: 'Server Monitoring',
        description: 'Real-time CPU, memory, and storage metrics with custom alert thresholds and email notifications.',
        icon: Activity,
    },
    {
        title: 'SSL Certificates',
        description: 'Automatic SSL certificate provisioning for custom domains. Keep your applications secure effortlessly.',
        icon: Shield,
    },
    {
        title: 'Firewall Rules',
        description: 'Easy port and IP-based access control. Secure your servers with allow and deny rules.',
        icon: Lock,
    },
    {
        title: 'Scheduled Tasks',
        description: 'Cron job management with monitoring, notifications for failures, and execution history.',
        icon: Clock,
    },
];

export default function FeaturesSection() {
    return (
        <section id="features" className="border-t border-white/5 bg-background py-24">
            <div className="mx-auto max-w-6xl px-6">
                {/* Section Header */}
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">Everything you need to manage servers</h2>
                    <p className="mt-4 text-lg text-muted-foreground">
                        A complete toolkit for deploying, monitoring, and managing your applications across any cloud provider.
                    </p>
                </div>

                {/* Features Grid */}
                <div className="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <div
                            key={feature.title}
                            className="group relative rounded-xl border border-white/5 bg-card/50 p-6 transition-colors hover:border-white/10 hover:bg-card"
                        >
                            {/* Icon */}
                            <div className="mb-4 flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <feature.icon className="size-6" />
                            </div>

                            {/* Content */}
                            <h3 className="text-lg font-semibold">{feature.title}</h3>
                            <p className="mt-2 text-sm text-muted-foreground">{feature.description}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
