import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';

interface Props {
    status?: string;
}

export default function ForgotPassword({ status }: Props): JSX.Element {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    return (
        <>
            <Head title="Wachtwoord vergeten" />
            <div className="flex min-h-screen items-center justify-center bg-background">
                <div className="w-full max-w-sm space-y-6 p-8">
                    <div className="space-y-1 text-center">
                        <h1 className="text-2xl font-bold">Wachtwoord vergeten</h1>
                        <p className="text-sm text-muted-foreground">
                            Vul je e-mailadres in om een reset-link te ontvangen.
                        </p>
                    </div>

                    {status && (
                        <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                            {status}
                        </div>
                    )}

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post('/forgot-password');
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-1">
                            <Label htmlFor="email">E-mailadres</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                autoComplete="email"
                                required
                            />
                            {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            Reset-link versturen
                        </Button>
                    </form>

                    <p className="text-center text-sm">
                        <a href="/login" className="text-muted-foreground hover:underline">
                            Terug naar inloggen
                        </a>
                    </p>
                </div>
            </div>
        </>
    );
}
