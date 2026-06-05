import PublicLayout from '@/layouts/PublicLayout';
import { Link } from '@inertiajs/react';

interface Municipality {
    id: number;
    slug: string;
    name: string;
}

interface Props {
    municipalities: Municipality[];
}

export default function Landing({ municipalities }: Props): JSX.Element {
    return (
        <PublicLayout>
            <div className="space-y-8">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold">Volgjeraad</h1>
                    <p className="text-muted-foreground">
                        Volg wat er speelt in de gemeenteraad. Automatisch samengevat, helder geschreven.
                    </p>
                </div>

                {municipalities.length > 0 ? (
                    <div className="space-y-3">
                        <h2 className="text-lg font-semibold">Gemeenten</h2>
                        <ul className="space-y-2">
                            {municipalities.map((municipality) => (
                                <li key={municipality.id}>
                                    <Link
                                        href={`/${municipality.slug}`}
                                        className="text-primary underline-offset-4 hover:underline"
                                    >
                                        {municipality.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : (
                    <p className="text-muted-foreground">Er zijn nog geen gemeenten beschikbaar.</p>
                )}
            </div>
        </PublicLayout>
    );
}
