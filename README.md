# Kabelkrant Home

Persoonlijke startpagina met bookmarks, achter één wachtwoord. PHP 8.4+, zonder framework; data in JSON.

## Structuur

```
app/        PHP-klassen (router, auth, opslag, validatie)
views/      templates
public/     webroot: index.php (enige toegangspunt), assets/, branding/, icons/
```

Buiten de repository (bewust, want niet voor git of de webserver bedoeld):

- `~/.env` met `SECRET_KEY`, optioneel `PAGE_PASSWORD` (beginwachtwoord), `REMEMBER_DAYS` en `DATA_DIR`
- `~/data/` met `bookmarks.json`, `settings.json` en `auth.json` (wachtwoord-hash)

## Installatie

1. Laat de webserver `public/` serveren en alle onbekende paden naar `public/index.php` sturen.
2. Maak `~/.env` aan met minstens `SECRET_KEY` en `PAGE_PASSWORD`.
3. Zorg dat PHP mag schrijven in `~/data/`, `public/icons/` en `public/branding/`.

Beheer staat op `/?p=admin`: bookmarks, icons, vormgeving (achtergrond en kleuren) en instellingen.

## Naamsvermelding

De achtergrondpatronen in `public/assets/js/backgrounds.js` zijn gebaseerd op
[Free SVG Backgrounds and Patterns](https://www.svgbackgrounds.com/set/free-svg-backgrounds-and-patterns/)
door Matt Visiwig, [SVGBackgrounds.com](https://www.svgbackgrounds.com/).
