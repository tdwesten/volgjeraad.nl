import OriValidator from '@/components/admin/OriValidator';
import StreamFinder from '@/components/admin/StreamFinder';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function MunicipalitiesCreate(): JSX.Element {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        ori_index: '',
        timezone: 'Europe/Amsterdam',
        active: true,
        youtube_channel_id: '',
    });

    const [slugEdited, setSlugEdited] = useState(false);
    const [oriEdited, setOriEdited] = useState(false);

    const handleNameChange = (value: string): void => {
        const derivedSlug = slugify(value);
        setData((current) => ({
            ...current,
            name: value,
            slug: slugEdited ? current.slug : derivedSlug,
            ori_index: oriEdited ? current.ori_index : derivedSlug ? `ori_${derivedSlug}` : '',
        }));
    };

    const submit = (e: React.FormEvent): void => {
        e.preventDefault();
        post('/admin/municipalities');
    };

    return (
        <AdminLayout>
            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Nieuwe gemeente</h1>
                    <Link href="/admin/municipalities" className="text-sm text-muted-foreground hover:underline">
                        &larr; Alle gemeenten
                    </Link>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Naam</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => handleNameChange(e.target.value)}
                            autoFocus
                        />
                        {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="slug">Slug</Label>
                        <Input
                            id="slug"
                            value={data.slug}
                            onChange={(e) => {
                                setSlugEdited(true);
                                setData('slug', e.target.value);
                            }}
                        />
                        <p className="text-xs text-muted-foreground">Kleine letters, cijfers en streepjes. Gebruikt in de publieke URL.</p>
                        {errors.slug && <p className="text-sm text-red-600">{errors.slug}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="ori_index">ORI-index</Label>
                        <Input
                            id="ori_index"
                            value={data.ori_index}
                            onChange={(e) => {
                                setOriEdited(true);
                                setData('ori_index', e.target.value);
                            }}
                        />
                        {errors.ori_index && <p className="text-sm text-red-600">{errors.ori_index}</p>}
                        <OriValidator oriIndex={data.ori_index} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="timezone">Tijdzone</Label>
                        <Input
                            id="timezone"
                            value={data.timezone}
                            onChange={(e) => setData('timezone', e.target.value)}
                        />
                        {errors.timezone && <p className="text-sm text-red-600">{errors.timezone}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="youtube_channel_id">YouTube-kanaal-ID</Label>
                        <Input
                            id="youtube_channel_id"
                            value={data.youtube_channel_id}
                            onChange={(e) => setData('youtube_channel_id', e.target.value)}
                            placeholder="UCxxxxxxxxxxxxxxxxxxxxxx"
                        />
                        {errors.youtube_channel_id && <p className="text-sm text-red-600">{errors.youtube_channel_id}</p>}
                        <StreamFinder name={data.name} onUse={(channelId) => setData('youtube_channel_id', channelId)} />
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            id="active"
                            type="checkbox"
                            checked={data.active}
                            onChange={(e) => setData('active', e.target.checked)}
                            className="h-4 w-4 rounded border-input"
                        />
                        <Label htmlFor="active" className="cursor-pointer">Actief</Label>
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Opslaan…' : 'Gemeente toevoegen'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href="/admin/municipalities">Annuleren</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
