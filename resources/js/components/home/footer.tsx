import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

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

export function CtaSection() {
    return (
        <section className="border-t border-white/5 bg-gradient-to-b from-background to-muted/30 py-24">
            <div className="mx-auto max-w-3xl px-6 text-center">
                <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">Ready to simplify your server management?</h2>
                <p className="mt-4 text-lg text-muted-foreground">
                    Join developers who've escaped the nightmare. Deploy your first server in minutes.
                </p>

                <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Button size="lg" asChild className="h-12 px-8 text-base">
                        <Link href={register()}>
                            Get Started Free
                            <ArrowRight className="ml-2 size-4" />
                        </Link>
                    </Button>
                </div>

                <p className="mt-6 text-sm text-muted-foreground">No credit card required. Start with 1 free server.</p>
            </div>
        </section>
    );
}

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="border-t border-white/5 bg-background py-12">
            <div className="mx-auto max-w-6xl px-6">
                <div className="flex flex-col items-center justify-between gap-8 md:flex-row">
                    {/* Logo and Copyright */}
                    <div className="flex flex-col items-center gap-4 md:flex-row md:gap-6">
                        <Logo />
                        <span className="text-sm text-muted-foreground">&copy; {currentYear} BrokeForge. All rights reserved.</span>
                    </div>

                    {/* Links */}
                    <nav className="flex items-center gap-6">
                        <a href="#features" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Features
                        </a>
                        <a href="#pricing" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Pricing
                        </a>
                        <Link href="/terms" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Terms
                        </Link>
                        <Link href="/privacy" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Privacy
                        </Link>
                    </nav>
                </div>
            </div>
        </footer>
    );
}
