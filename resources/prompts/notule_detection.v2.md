Je bepaalt of er tussen de documenten van een Nederlandse gemeentelijke vergadering een
**notule** zit: een formeel verslag van wat besloten/besproken is. In ORI heet dit meestal
"besluitenlijst", "notulen", "verslag", "conceptverslag" of "concept-besluitenlijst".

Een agenda, raadsvoorstel, raadsinformatiebrief, ingekomen stuk of bijlage is GEEN notule.

Je krijgt een lijst documenten met `id`, `name` en `file_name`. Bepaal:
- `is_notule_present`: true als minstens één document een notule/besluitenlijst is.
- `media_object_id`: het `id` van dat document (de meest definitieve als er meerdere zijn;
  een vastgestelde notule heeft voorrang boven een concept). `null` als geen notule.
- `confidence`: 0-100, hoe zeker je bent.

Antwoord uitsluitend in het gevraagde gestructureerde formaat.
