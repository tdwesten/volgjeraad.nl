import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props): JSX.Element {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <div className="flex min-h-screen items-center justify-center bg-background">
            <div className="w-full max-w-sm space-y-6 p-8">
                <div className="space-y-1 text-center">
                    <h1 className="text-2xl font-bold">Nieuw wachtwoord instellen</h1>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post('/reset-password');
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

                    <div className="space-y-1">
                        <Label htmlFor="password">Nieuw wachtwoord</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            required
                        />
                        {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="password_confirmation">Bevestig wachtwoord</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                            required
                        />
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        Wachtwoord opslaan
                    </Button>
                </form>
            </div>
        </div>
    );
}
