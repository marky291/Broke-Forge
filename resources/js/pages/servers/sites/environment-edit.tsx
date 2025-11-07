import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { PageHeader } from '@/components/ui/page-header';
import { Textarea } from '@/components/ui/textarea';
import SiteLayout from '@/layouts/server/site-layout';
import { Head, useForm } from '@inertiajs/react';
import { FileEdit, Loader2, Save } from 'lucide-react';
import { useState } from 'react';

type SiteFramework = {
    name: string;
    env: {
        file_path: string | null;
        supports: boolean;
    };
    requirements: {
        database: boolean;
        redis: boolean;
        nodejs: boolean;
        composer: boolean;
    };
};

type Site = {
    id: number;
    domain?: string | null;
    status?: string;
    health?: string;
    git_status?: string | null;
    git_provider?: string | null;
    git_repository?: string | null;
    git_branch?: string | null;
    last_deployed_at?: string | null;
    site_framework: SiteFramework;
};

type Server = {
    id: number;
    vanity_name: string;
    provider?: string;
    connection: string;
    public_ip?: string;
    private_ip?: string;
};

export default function EnvironmentEdit({
    server,
    site,
    envContent,
}: {
    server: Server;
    site: Site;
    envContent: string;
}) {
    const { data, setData, put, processing, errors } = useForm({
        content: envContent,
    });
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(`/servers/${server.id}/sites/${site.id}/environment`, {
            preserveScroll: true,
            onSuccess: () => {
                setHasUnsavedChanges(false);
            },
        });
    };

    const handleContentChange = (value: string) => {
        setData('content', value);
        setHasUnsavedChanges(value !== envContent);
    };

    return (
        <SiteLayout server={server} site={site}>
            <Head title={`Environment — ${site.domain || 'Site'}`} />
            <PageHeader
                title="Edit Environment"
                description={`Edit ${site.site_framework.env.file_path} for ${site.domain || 'this site'}`}
            >
                <form onSubmit={handleSubmit}>
                    <CardContainer
                        title={`Editing: ${site.site_framework.env.file_path}`}
                        description="Make changes to your environment configuration"
                        icon={<FileEdit className="h-4 w-4" />}
                    >
                        <div className="space-y-4">
                            <div>
                                <Textarea
                                    id="content"
                                    value={data.content}
                                    onChange={(event) => handleContentChange(event.target.value)}
                                    placeholder={
                                        data.content === ''
                                            ? 'Environment file is empty. Add your configuration here...'
                                            : ''
                                    }
                                    className="min-h-[500px] font-mono text-sm"
                                    disabled={processing}
                                />
                                {errors.content && <p className="mt-2 text-sm text-destructive">{errors.content}</p>}
                            </div>

                            <div className="flex items-center justify-between gap-4 border-t border-border pt-4">
                                <div className="text-sm text-muted-foreground">
                                    {hasUnsavedChanges && (
                                        <span className="text-yellow-600 dark:text-yellow-400">● Unsaved changes</span>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing || !hasUnsavedChanges}>
                                    {processing ? (
                                        <span className="inline-flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Saving...
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-2">
                                            <Save className="h-4 w-4" />
                                            Save Changes
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </CardContainer>
                </form>
            </PageHeader>
        </SiteLayout>
    );
}
