# Kabelkrant Home

Persoonlijke startpagina met bookmarks, achter één wachtwoord. PHP 8.4+, zonder framework; data in JSON.

## Structuur

```
app/        PHP-klassen (router, auth, opslag, validatie)
views/      templates
public/     webroot: index.php (enige toegangspunt), assets/, branding/, icons/
```

Buiten de repository, in de map erboven (bewust, want niet voor git of de webserver bedoeld).
De paden zijn relatief aan de projectmap, dus ze verhuizen mee als het project verplaatst wordt:

- `../.env` met `SECRET_KEY`, optioneel `PAGE_PASSWORD` (beginwachtwoord), `REMEMBER_DAYS` `DATA_DIR` (ander pad voor de datamap) en `UPDATE_REPO`/`UPDATE_BRANCH` (bron voor bijwerken, standaard `Kabelkrant/kabelkrant-home` en `main`)
- `../data/` met `bookmarks.json`, `settings.json` en `auth.json` (wachtwoord-hash)

## Installatie

1. Laat de webserver `public/` serveren en alle onbekende paden naar `public/index.php` sturen.
2. Maak `../.env` aan met minstens `SECRET_KEY` en `PAGE_PASSWORD`.
3. Zorg dat PHP mag schrijven in `../data/`, `public/icons/` en `public/branding/`.

Beheer staat op `/?p=admin`: bookmarks, icons, vormgeving (achtergrond en kleuren) en instellingen.

## Bijwerken

Onder Instellingen → Software bijwerken haalt de site de nieuwste code van GitHub op. Is de installatie een git-clone, dan gebeurt dat met `git pull --ff-only`. Anders wordt de zip gedownload en over de projectmap uitgepakt, met eerst een backup van de vorige versie in `../data/backups/` (de laatste drie blijven bewaard). `../.env`, `../data/` en geüploade afbeeldingen blijven altijd ongemoeid. Iconen in `public/icons/` gelden bij de zip-methode als gebruikersdata: bestaande iconen worden nooit overschreven of verwijderd, alleen nieuwe iconen uit de repository komen erbij. Bij de git-methode verwijdert `git pull` wel iconen die uit de repository zijn gehaald.

## Naamsvermelding

De achtergrondpatronen in `public/assets/js/backgrounds.js` zijn gebaseerd op
[Free SVG Backgrounds and Patterns](https://www.svgbackgrounds.com/set/free-svg-backgrounds-and-patterns/)
door Matt Visiwig, [SVGBackgrounds.com](https://www.svgbackgrounds.com/).
