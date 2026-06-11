import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';

interface Props {
    errors?: {
        email?: string;
        password?: string;
    };
}

export default function Login({ errors }: Props): JSX.Element {
    const { data, setData, post, processing } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <>
            <Head title="Inloggen" />
            <div className="flex min-h-screen items-center justify-center bg-background">
                <div className="w-full max-w-sm space-y-6 p-8">
                    <div className="space-y-1 text-center">
                        <h1 className="text-2xl font-bold">Volg je raad — Beheer</h1>
                        <p className="text-sm text-muted-foreground">Log in om door te gaan</p>
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post('/login');
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
                            {errors?.email && <p className="text-sm text-destructive">{errors.email}</p>}
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="password">Wachtwoord</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="current-password"
                                required
                            />
                            {errors?.password && <p className="text-sm text-destructive">{errors.password}</p>}
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            Inloggen
                        </Button>
                    </form>

                    <p className="text-center text-sm">
                        <a href="/forgot-password" className="text-muted-foreground hover:underline">
                            Wachtwoord vergeten?
                        </a>
                    </p>
                </div>
            </div>
        </>
    );
}
