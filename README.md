# Lizenzverwaltung – Wörderner Fischereiverein

Diese Anwendung ermöglicht die Verwaltung der Jahreslizenzen des Wörderner Fischereivereins.

## Funktionen

- Jahresnavigation mit automatischem Anlegen neuer Lizenz-Tabellen (`lizenzen_YYYY`) und zentraler Bootsliste.
- Übersicht aller Lizenzen des ausgewählten Jahres inklusive Stammdaten.
- CRUD über Modals für Lizenznehmer, Lizenz, Bootsdaten.
- Verlängerung in ein neues Jahr inkl. automatischer Preisauswahl und Datenübernahme.
- Zentrale Formularvalidierung mit sofortigem Feedback.
- PLZ-Autovervollständigung über die `plz_orte`-Tabelle.

## Lizenztypen

Folgende Lizenzarten stehen standardmäßig zur Verfügung und können jährlich bepreist werden:

- Angel
- Daubel
- Boot
- Intern
- Kinder
- Jugend

## Installation

1. `schema.sql` einspielen:
   ```bash
   mysql -u root -p < db/schema.sql
   ```
2. Datenbankzugang in `config.php` anpassen.
3. Projektordner auf einen PHP-fähigen Webserver legen (z. B. Apache/Nginx oder PHP Built-in Server).

## Entwicklung

- Frontend: Plain HTML/CSS/JS
- Backend: PHP mit PDO (MySQL)
- API-Endpunkte: `api.php`

## Tests

Da es sich um eine klassische PHP-Anwendung handelt, stehen keine automatisierten Tests zur Verfügung. Prüfe die Funktionen manuell im Browser.
