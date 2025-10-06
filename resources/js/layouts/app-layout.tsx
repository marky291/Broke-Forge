import { MainHeader } from '@/components/main-header';
import {
    Breadcrumb,
    BreadcrumbItem as BreadcrumbComponent,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <div className="flex min-h-screen flex-col bg-background">
        <MainHeader />

        {/* Breadcrumbs Section */}
        {breadcrumbs && breadcrumbs.length > 0 && (
            <div className="border-b bg-muted/30">
                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex h-12 items-center">
                        <Breadcrumb>
                            <BreadcrumbList>
                                {breadcrumbs.map((breadcrumb, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        {index > 0 && <BreadcrumbSeparator />}
                                        <BreadcrumbComponent>
                                            {index === breadcrumbs.length - 1 ? (
                                                <BreadcrumbPage>{breadcrumb.title}</BreadcrumbPage>
                                            ) : (
                                                <BreadcrumbLink href={breadcrumb.href}>{breadcrumb.title}</BreadcrumbLink>
                                            )}
                                        </BreadcrumbComponent>
                                    </div>
                                ))}
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </div>
            </div>
        )}

        {/* Main Content */}
        <main className="flex-1">
            <div className="container mx-auto max-w-7xl px-4 py-8">{children}</div>
        </main>
    </div>
);
