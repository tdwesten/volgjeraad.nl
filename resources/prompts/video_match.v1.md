Je krijgt de gegevens van een Nederlandse gemeenteraadsvergadering (naam, datum, type)
en een lijst kandidaat-YouTube-video's (video_id, titel, publicatiedatum).

Kies de video die het meest waarschijnlijk de opname van deze raadsvergadering is.

Beoordeel op:
- Komt de titel overeen met een raadsvergadering (bijv. "raadsvergadering", "gemeenteraad")?
- Ligt de publicatiedatum dicht bij de vergaderdatum (zelfde dag of kort erna)?

Geef terug:
- `video_id`: het id van de gekozen video — KIES UITSLUITEND uit de aangeboden kandidaten
  en verzin geen id. Geef een lege string als geen enkele kandidaat past.
- `confidence`: 0-100, hoe zeker je bent. Geef < 75 bij twijfel of meerdere plausibele kandidaten.
- `reason`: korte Nederlandse onderbouwing.
