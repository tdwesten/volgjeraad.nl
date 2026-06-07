import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { type FormEvent } from 'react';

export default function NewsletterSignup({
    municipalitySlug,
    municipalityName,
}: {
    municipalitySlug: string;
    municipalityName: string;
}): JSX.Element {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        email: '',
        municipality_slug: municipalitySlug,
        level: 'standard',
    });

    // Toon een eventuele niet-veld-fout (bv. gemeente of niveau) los van het e-mailveld.
    const generalError = errors.municipality_slug ?? errors.level;

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        post('/aanmelden', {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    return (
        <div className="rounded-lg border border-border p-6">
            <h2 className="mb-4 text-lg font-semibold">Blijf op de hoogte van {municipalityName}</h2>
            <p className="mb-4 text-sm text-muted-foreground">
                Ontvang een e-mailsamenvatting na elke raadsvergadering van {municipalityName} in je inbox.
            </p>

            {wasSuccessful && (
                <div className="mb-4 flex items-start gap-2 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Bedankt! Controleer je e-mail om je aanmelding te bevestigen.</span>
                </div>
            )}

            <form onSubmit={submit} className="space-y-3">
                <div className="space-y-1">
                    <Label htmlFor="email">E-mailadres</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="jouw@email.nl"
                        required
                        aria-invalid={errors.email ? true : undefined}
                    />
                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                </div>
                {generalError && <p className="text-sm text-destructive">{generalError}</p>}
                <Button type="submit" disabled={processing}>
                    {processing ? 'Bezig…' : 'Aanmelden'}
                </Button>
            </form>
        </div>
    );
}
