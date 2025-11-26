import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Menu, X } from 'lucide-react';
import { useState } from 'react';

function Logo() {
    return (
        <Link href="/" className="flex items-center gap-2">
            <div className="flex size-8 items-center justify-center rounded-lg bg-primary">
                <span className="text-sm font-bold text-primary-foreground">BF</span>
            </div>
            <span className="text-lg font-semibold">BrokeForge</span>
        </Link>
    );
}

function Navigation() {
    const { auth } = usePage<SharedData>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    return (
        <header className="fixed top-0 right-0 left-0 z-50 border-b border-white/10 bg-background/80 backdrop-blur-md">
            <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <Logo />

                {/* Desktop Navigation */}
                <div className="hidden items-center gap-8 md:flex">
                    <a href="#features" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                        Features
                    </a>
                    <a href="#pricing" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                        Pricing
                    </a>
                </div>

                {/* Desktop Auth Buttons */}
                <div className="hidden items-center gap-3 md:flex">
                    {auth.user ? (
                        <Button asChild>
                            <Link href="/dashboard">Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>Get Started</Link>
                            </Button>
                        </>
                    )}
                </div>

                {/* Mobile Menu Button */}
                <button className="md:hidden" onClick={() => setMobileMenuOpen(!mobileMenuOpen)} aria-label="Toggle menu">
                    {mobileMenuOpen ? <X className="size-6" /> : <Menu className="size-6" />}
                </button>
            </nav>

            {/* Mobile Menu */}
            {mobileMenuOpen && (
                <div className="border-t border-white/10 bg-background px-6 py-4 md:hidden">
                    <div className="flex flex-col gap-4">
                        <a
                            href="#features"
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                            onClick={() => setMobileMenuOpen(false)}
                        >
                            Features
                        </a>
                        <a
                            href="#pricing"
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                            onClick={() => setMobileMenuOpen(false)}
                        >
                            Pricing
                        </a>
                        <div className="flex flex-col gap-2 pt-4">
                            {auth.user ? (
                                <Button asChild>
                                    <Link href="/dashboard">Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={register()}>Get Started</Link>
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}

function DashboardMockup() {
    return (
        <div className="relative mx-auto mt-16 w-full max-w-4xl px-6 lg:mt-20">
            {/* Browser Window Frame */}
            <div className="overflow-hidden rounded-xl border border-white/10 bg-card shadow-2xl">
                {/* Browser Chrome */}
                <div className="flex items-center gap-2 border-b border-white/10 bg-muted/50 px-4 py-3">
                    <div className="flex gap-1.5">
                        <div className="size-3 rounded-full bg-red-500/80" />
                        <div className="size-3 rounded-full bg-yellow-500/80" />
                        <div className="size-3 rounded-full bg-green-500/80" />
                    </div>
                    <div className="ml-4 flex-1 rounded-md bg-background/50 px-4 py-1 text-xs text-muted-foreground">
                        brokeforge.app/dashboard
                    </div>
                </div>

                {/* Dashboard Content Placeholder */}
                <div className="relative aspect-[16/10] bg-gradient-to-br from-background via-background to-muted/20 p-6">
                    {/* Simulated Dashboard UI */}
                    <div className="grid h-full grid-cols-12 gap-4">
                        {/* Sidebar */}
                        <div className="col-span-2 rounded-lg border border-white/5 bg-card/50 p-3">
                            <div className="mb-4 h-6 w-16 rounded bg-primary/20" />
                            <div className="space-y-2">
                                {[...Array(5)].map((_, i) => (
                                    <div key={i} className="h-4 rounded bg-white/5" />
                                ))}
                            </div>
                        </div>

                        {/* Main Content */}
                        <div className="col-span-10 space-y-4">
                            {/* Header */}
                            <div className="flex items-center justify-between">
                                <div className="h-8 w-32 rounded bg-white/10" />
                                <div className="h-8 w-24 rounded bg-primary/30" />
                            </div>

                            {/* Stats Row */}
                            <div className="grid grid-cols-4 gap-4">
                                {[...Array(4)].map((_, i) => (
                                    <div key={i} className="rounded-lg border border-white/5 bg-card/30 p-4">
                                        <div className="mb-2 h-4 w-20 rounded bg-white/10" />
                                        <div className="h-8 w-16 rounded bg-white/5" />
                                    </div>
                                ))}
                            </div>

                            {/* Server Cards */}
                            <div className="grid grid-cols-2 gap-4">
                                {[...Array(4)].map((_, i) => (
                                    <div key={i} className="rounded-lg border border-white/5 bg-card/30 p-4">
                                        <div className="mb-3 flex items-center gap-3">
                                            <div className="size-8 rounded bg-primary/20" />
                                            <div className="flex-1">
                                                <div className="mb-1 h-4 w-24 rounded bg-white/10" />
                                                <div className="h-3 w-16 rounded bg-white/5" />
                                            </div>
                                            <div className="size-2 rounded-full bg-green-500" />
                                        </div>
                                        <div className="space-y-2">
                                            <div className="h-2 rounded-full bg-white/5">
                                                <div className="h-2 w-3/4 rounded-full bg-primary/30" />
                                            </div>
                                            <div className="h-2 rounded-full bg-white/5">
                                                <div className="h-2 w-1/2 rounded-full bg-blue-500/30" />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Gradient Overlay */}
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-background/80 via-transparent to-transparent" />
                </div>
            </div>

            {/* Decorative Glow */}
            <div className="absolute -inset-x-20 -top-20 -z-10 transform-gpu overflow-hidden blur-3xl" aria-hidden="true">
                <div
                    className="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-primary/20 to-primary/5 opacity-30 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"
                    style={{
                        clipPath:
                            'polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)',
                    }}
                />
            </div>
        </div>
    );
}

export default function HeroSection() {
    return (
        <section className="relative min-h-screen overflow-hidden bg-background">
            <Navigation />

            {/* Hero Content */}
            <div className="mx-auto max-w-6xl px-6 pt-32 text-center lg:pt-40">
                {/* Badge */}
                <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-muted/50 px-4 py-1.5 text-sm">
                    <span className="size-2 rounded-full bg-green-500" />
                    <span className="text-muted-foreground">Deploy servers in minutes, not hours</span>
                </div>

                {/* Headline */}
                <h1 className="mx-auto max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                    Managing Servers is a{' '}
                    <span className="bg-gradient-to-r from-red-500 to-orange-500 bg-clip-text text-transparent">Nightmare.</span>
                </h1>

                <p className="mt-4 text-2xl font-medium text-muted-foreground sm:text-3xl">Does it have to be?</p>

                <p className="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                    BrokeForge simplifies server management with automated deployments, real-time monitoring, and seamless database management.
                    Connect your favorite cloud provider and ship faster.
                </p>

                {/* CTA Buttons */}
                <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Button size="lg" asChild className="h-12 px-8 text-base">
                        <Link href={register()}>
                            Get Started
                            <ArrowRight className="ml-2 size-4" />
                        </Link>
                    </Button>
                    <Button size="lg" variant="outline" asChild className="h-12 px-8 text-base">
                        <a href="#features">See Features</a>
                    </Button>
                </div>
            </div>

            {/* Dashboard Mockup */}
            <DashboardMockup />
        </section>
    );
}
