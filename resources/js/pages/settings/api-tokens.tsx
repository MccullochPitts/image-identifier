import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/api-tokens';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Form, Head, router, usePage } from '@inertiajs/react';
import { CheckIcon, CopyIcon, TrashIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API Tokens',
        href: index().url,
    },
];

interface Token {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

export default function ApiTokens({ tokens }: { tokens: Token[] }) {
    const page = usePage<{
        token?: string;
        tokenName?: string;
    }>();

    const newToken = page.props.token;
    const newTokenName = page.props.tokenName;

    // Derive dialog visibility from props (no state needed)
    const [showTokenDialog, setShowTokenDialog] = useState(!!newToken);
    const [copied, setCopied] = useState(false);

    // Update dialog visibility when token prop changes
    useEffect(() => {
        // This is valid because we're synchronizing with external state (Inertia props)
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setShowTokenDialog(!!newToken);
    }, [newToken]);

    const copyToClipboard = () => {
        if (newToken) {
            navigator.clipboard.writeText(newToken);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const deleteToken = (tokenId: number) => {
        if (
            confirm(
                'Are you sure you want to delete this token? This action cannot be undone.',
            )
        ) {
            router.delete(ApiTokenController.destroy({ tokenId }).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API Tokens" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="API Tokens"
                        description="Manage API tokens for programmatic access to your account"
                    />

                    <Alert>
                        <AlertDescription>
                            API tokens allow you to authenticate with the API.
                            Keep your tokens secure and never share them
                            publicly.
                        </AlertDescription>
                    </Alert>

                    {/* Create Token Form */}
                    <Form
                        {...ApiTokenController.store.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Token Name</Label>

                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="My API Token"
                                        required
                                        className="max-w-md"
                                    />

                                    {errors.name && (
                                        <p className="text-sm text-red-600">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <Button type="submit" disabled={processing}>
                                    Create Token
                                </Button>
                            </>
                        )}
                    </Form>

                    {/* Token List */}
                    {tokens.length > 0 ? (
                        <div className="space-y-4">
                            <h3 className="text-sm font-medium">
                                Your API Tokens
                            </h3>

                            <div className="space-y-2">
                                {tokens.map((token) => (
                                    <div
                                        key={token.id}
                                        className="flex items-center justify-between rounded-md border p-4"
                                    >
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">
                                                {token.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Created{' '}
                                                {new Date(
                                                    token.created_at,
                                                ).toLocaleDateString()}
                                                {token.last_used_at &&
                                                    ` • Last used ${new Date(token.last_used_at).toLocaleDateString()}`}
                                            </p>
                                        </div>

                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                deleteToken(token.id)
                                            }
                                        >
                                            <TrashIcon className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <p className="text-sm text-muted-foreground">
                                No API tokens yet. Create one to get started.
                            </p>
                        </div>
                    )}
                </div>
            </SettingsLayout>

            {/* New Token Dialog */}
            <Dialog open={showTokenDialog} onOpenChange={setShowTokenDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Token Created Successfully</DialogTitle>
                        <DialogDescription>
                            Make sure to copy your API token now. You won't be
                            able to see it again!
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Token Name</Label>
                            <p className="text-sm font-medium">
                                {newTokenName}
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label>Token</Label>
                            <div className="flex gap-2">
                                <Input
                                    value={newToken || ''}
                                    readOnly
                                    className="font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={copyToClipboard}
                                    className="shrink-0"
                                >
                                    <Transition
                                        show={copied}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0 scale-90"
                                        enterTo="opacity-100 scale-100"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0 scale-90"
                                    >
                                        <CheckIcon className="h-4 w-4" />
                                    </Transition>
                                    <Transition
                                        show={!copied}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0 scale-90"
                                        enterTo="opacity-100 scale-100"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0 scale-90"
                                    >
                                        <CopyIcon className="h-4 w-4" />
                                    </Transition>
                                </Button>
                            </div>
                        </div>

                        <Alert>
                            <AlertDescription>
                                Store this token securely. It will not be shown
                                again.
                            </AlertDescription>
                        </Alert>
                    </div>

                    <DialogFooter>
                        <Button onClick={() => setShowTokenDialog(false)}>
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
