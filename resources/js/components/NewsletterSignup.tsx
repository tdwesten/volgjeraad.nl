import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';

export default function NewsletterSignup({ municipalitySlug }: { municipalitySlug: string }): JSX.Element {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        municipality_slug: municipalitySlug,
        level: 'standard',
    });

    return (
        <div className="rounded-lg border border-border p-6">
            <h2 className="mb-4 text-lg font-semibold">Blijf op de hoogte</h2>
            <p className="mb-4 text-sm text-muted-foreground">
                Ontvang een e-mailsamenvatting na elke raadsvergadering.
            </p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post('/aanmelden');
                }}
                className="space-y-3"
            >
                <div className="space-y-1">
                    <Label htmlFor="email">E-mailadres</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="jouw@email.nl"
                        required
                    />
                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                </div>
                <Button type="submit" disabled={processing}>
                    Aanmelden
                </Button>
            </form>
        </div>
    );
}
