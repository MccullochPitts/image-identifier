import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { index as apiTokensIndex } from '@/routes/api-tokens';
import { Transition } from '@headlessui/react';
import { Form, Link, router, usePage } from '@inertiajs/react';
import { CheckIcon, Code2, CopyIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function ApiQuickStart() {
    const page = usePage<{
        token?: string;
        tokenName?: string;
    }>();

    const newToken = page.props.token;
    const newTokenName = page.props.tokenName;

    const [showTokenDialog, setShowTokenDialog] = useState(!!newToken);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
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

    return (
        <>
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Code2 className="h-5 w-5 text-primary" />
                        <CardTitle>Get Started with API</CardTitle>
                    </div>
                    <CardDescription>
                        Create an API token to start integrating image
                        processing
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Form
                        {...ApiTokenController.store.form()}
                        className="flex gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex-1">
                                    <Input
                                        name="name"
                                        placeholder="Token name (e.g., My App)"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Create
                                </Button>
                            </>
                        )}
                    </Form>

                    <div className="text-sm text-muted-foreground">
                        Or{' '}
                        <Link
                            href={apiTokensIndex()}
                            className="text-foreground underline underline-offset-4 hover:text-primary"
                        >
                            manage all API tokens
                        </Link>
                    </div>
                </CardContent>
            </Card>

            {/* Token Display Dialog */}
            <Dialog open={showTokenDialog} onOpenChange={setShowTokenDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Token Created</DialogTitle>
                        <DialogDescription>
                            Copy your API token now. You won't be able to see it
                            again!
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
                    </div>

                    <DialogFooter>
                        <Button
                            onClick={() => {
                                setShowTokenDialog(false);
                                router.visit(apiTokensIndex());
                            }}
                        >
                            View All Tokens
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
