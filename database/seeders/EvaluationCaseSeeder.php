<?php

namespace Database\Seeders;

use App\Models\EvaluationCase;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

// wired in DatabaseSeeder via Item 15

class EvaluationCaseSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::where('slug', 'brummen')->first();

        if (! $municipality) {
            $this->command->warn('Municipality "brummen" not found. Skipping EvaluationCaseSeeder.');

            return;
        }

        $cases = [
            [
                'name' => 'Bestemmingsplan wijziging perceel Zutphenseweg',
                'source_text' => <<<'TEXT'
De raad bespreekt het voorstel voor het wijzigen van het bestemmingsplan voor perceel Zutphenseweg 23 te Brummen.
Het college stelt voor om de bestemming te wijzigen van "agrarisch" naar "wonen" om de bouw van twee woningen mogelijk te maken.
De wijziging past binnen het gemeentelijke woonbeleid en voldoet aan de provinciale omgevingsverordening.
De commissie Ruimte heeft positief geadviseerd over het voorstel. Er zijn drie zienswijzen ingediend, waarvan één gegrond is verklaard.
Als gevolg hiervan is de bouwhoogte beperkt tot maximaal 8 meter in plaats van de oorspronkelijk voorgestelde 10 meter.
TEXT,
                'expected_facts' => [
                    'Zutphenseweg 23',
                    'agrarisch',
                    'wonen',
                    'twee woningen',
                    'bouwhoogte',
                    '8 meter',
                ],
                'forbidden_claims' => [
                    '10 meter',
                    'vijf woningen',
                ],
                'level' => 'standard',
                'active' => true,
            ],
            [
                'name' => 'Jaarrekening 2025 Brummen',
                'source_text' => <<<'TEXT'
De raad stelt de jaarrekening 2025 vast. Het resultaat over 2025 bedraagt € 1,2 miljoen positief.
Het positieve resultaat wordt voor € 800.000 toegevoegd aan de algemene reserve.
De overige € 400.000 wordt gereserveerd voor de renovatie van de Bovenberghal.
De accountant heeft een goedkeurende verklaring afgegeven.
TEXT,
                'expected_facts' => [
                    'jaarrekening 2025',
                    '1,2 miljoen',
                    'algemene reserve',
                    'Bovenberghal',
                    'accountant',
                ],
                'forbidden_claims' => [
                    '2 miljoen',
                    'negatief resultaat',
                ],
                'level' => 'standard',
                'active' => true,
            ],
            [
                'name' => 'Motie duurzaamheid zonnepanelen scholen',
                'source_text' => <<<'TEXT'
De raad behandelt een motie van D66 en GroenLinks over het plaatsen van zonnepanelen op gemeentelijke schoolgebouwen.
De motie verzoekt het college om vóór 1 juli 2026 een plan van aanpak op te stellen voor het verduurzamen van alle vijf gemeentelijke basisscholen.
Het college ondersteunt de motie en geeft aan dat er al gesprekken zijn met energiecoöperatie Brummen Duurzaam.
De motie wordt aangenomen met 12 stemmen voor en 3 stemmen tegen.
TEXT,
                'expected_facts' => [
                    'zonnepanelen',
                    'schoolgebouwen',
                    '1 juli 2026',
                    'vijf',
                    '12 stemmen',
                ],
                'forbidden_claims' => [
                    '15 stemmen voor',
                    'unaniem',
                ],
                'level' => 'simple',
                'active' => true,
            ],
            [
                'name' => 'TODO: Verkeersplan centrum Brummen',
                'source_text' => 'TODO: vervang door echte besluitenlijst-tekst over het verkeersplan centrum Brummen.',
                'expected_facts' => [
                    'TODO: voeg verwachte feiten toe',
                ],
                'forbidden_claims' => null,
                'level' => 'standard',
                'active' => false,
            ],
            [
                'name' => 'TODO: Subsidieregeling lokale initiatieven',
                'source_text' => 'TODO: vervang door echte besluitenlijst-tekst over de subsidieregeling lokale initiatieven.',
                'expected_facts' => [
                    'TODO: voeg verwachte feiten toe',
                ],
                'forbidden_claims' => null,
                'level' => 'simple',
                'active' => false,
            ],
        ];

        foreach ($cases as $case) {
            EvaluationCase::updateOrCreate(
                ['municipality_id' => $municipality->id, 'name' => $case['name']],
                array_merge($case, ['municipality_id' => $municipality->id]),
            );
        }

        $this->command->info('Seeded '.count($cases).' evaluation cases for Brummen.');
    }
}
