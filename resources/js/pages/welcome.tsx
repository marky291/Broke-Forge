import CloudProvidersSection from '@/components/home/cloud-providers';
import FeaturesSection from '@/components/home/features-section';
import Footer, { CtaSection } from '@/components/home/footer';
import HeroSection from '@/components/home/hero-section';
import PricingSection from '@/components/home/pricing-section';
import { Head } from '@inertiajs/react';

type SubscriptionPlan = {
    id: number;
    name: string;
    slug: string;
    amount: number;
    interval: 'month' | 'year';
    features: string[];
    formatted_price: string;
};

type FreePlan = {
    name: string;
    server_limit: number;
    features: string[];
};

type WelcomeProps = {
    plans: SubscriptionPlan[];
    freePlan: FreePlan;
};

export default function Welcome({ plans, freePlan }: WelcomeProps) {
    return (
        <>
            <Head title="Server Management Made Simple">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
                <meta
                    name="description"
                    content="BrokeForge simplifies server management with automated deployments, real-time monitoring, and seamless database management. Connect your favorite cloud provider and ship faster."
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <HeroSection />
                <FeaturesSection />
                <CloudProvidersSection />
                <PricingSection plans={plans} freePlan={freePlan} />
                <CtaSection />
                <Footer />
            </div>
        </>
    );
}
