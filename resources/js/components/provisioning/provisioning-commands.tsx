import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Copy, Terminal } from 'lucide-react';
import { useState } from 'react';

interface ProvisionData {
    command: string;
    root_password: string;
}

interface ProvisioningCommandsProps {
    provisionData: ProvisionData | null;
    serverName: string;
    serverIp: string;
}

export default function ProvisioningCommands({ provisionData, serverName, serverIp }: ProvisioningCommandsProps) {
    const [copiedField, setCopiedField] = useState<string | null>(null);

    if (!provisionData) {
        return null;
    }

    const copyToClipboard = async (text: string, field: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopiedField(field);
            setTimeout(() => setCopiedField(null), 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    };

    return (
        <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-900/10">
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Terminal className="h-5 w-5 text-blue-600" />
                    <CardTitle className="text-blue-900 dark:text-blue-100">Server Provisioning Required</CardTitle>
                </div>
                <CardDescription className="text-blue-700 dark:text-blue-200">
                    Run the following command on your server ({serverName} - {serverIp}) to complete setup
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <Alert>
                    <AlertDescription>
                        <strong>Important:</strong> This command must be executed as root on your server. Make sure you have SSH access before
                        proceeding.
                    </AlertDescription>
                </Alert>

                <div className="space-y-3">
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <label className="text-sm font-medium">Root Password</label>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => copyToClipboard(provisionData.root_password, 'password')}
                                className="h-7"
                            >
                                <Copy className="mr-1 h-3 w-3" />
                                {copiedField === 'password' ? 'Copied!' : 'Copy'}
                            </Button>
                        </div>
                        <code className="block w-full rounded border bg-gray-100 p-3 font-mono text-sm dark:bg-gray-800">
                            {provisionData.root_password}
                        </code>
                    </div>

                    <Separator />

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <label className="text-sm font-medium">Provisioning Command</label>
                            <Button variant="outline" size="sm" onClick={() => copyToClipboard(provisionData.command, 'command')} className="h-7">
                                <Copy className="mr-1 h-3 w-3" />
                                {copiedField === 'command' ? 'Copied!' : 'Copy'}
                            </Button>
                        </div>
                        <code className="block w-full rounded border bg-gray-100 p-3 font-mono text-sm break-all dark:bg-gray-800">
                            {provisionData.command}
                        </code>
                    </div>
                </div>

                <div className="border-t pt-2 text-xs text-muted-foreground">
                    <p>
                        <strong>Steps:</strong>
                    </p>
                    <ol className="mt-1 list-inside list-decimal space-y-1">
                        <li>SSH into your server as root</li>
                        <li>Copy and paste the provisioning command above</li>
                        <li>Press Enter to execute the script</li>
                        <li>Wait for the automatic provisioning to complete</li>
                    </ol>
                </div>
            </CardContent>
        </Card>
    );
}
