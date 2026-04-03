# Production Deploy

This rollout matches the current code on `main` and the provided production dump `d04564c5.sql`.

## Before the maintenance window

1. Export the full production database in phpMyAdmin.
2. Download the current production files via FTP.
   At minimum keep copies of `config.php` and `.htaccess`.
3. Prepare the runtime upload set from this workspace.

## During the maintenance window

1. Stop data entry and keep users out of production.
2. Run [db/prod-upgrade.sql](/var/www/html/wfv-neu/db/prod-upgrade.sql) in phpMyAdmin.
3. Review the query results at the end of the script.
   The rollout should stop here until:
   - no unexpected `lizenzen_2026` rows are left without `lizenznummer`
   - duplicate Standard-group numbers are resolved manually
4. Upload the current runtime files via FTP.

## Upload scope

Upload:
- PHP entrypoints such as `index.php`, `api.php`, `admin.php`, `export.php`, `boats.php`, `neuwerber.php`, `schluessel.php`, `sperrliste.php`
- `assets/`
- `lib/`
- `partials/`

Do not upload:
- `config.php`
- `d04564c5.sql`
- `.git/`
- other local-only files

## Manual duplicate cleanup

The SQL script intentionally reports duplicate 2026 license numbers instead of auto-renumbering them.

Use the duplicate report plus the helper query at the end of `db/prod-upgrade.sql` to choose the next free Standard-group number, then fix the affected row with a manual statement like:

```sql
UPDATE `lizenzen_2026`
SET `lizenznummer` = 150
WHERE `id` = 104;
```

## Verification after upload

Open:
- `index.php`
- `admin.php`
- `neuwerber.php`
- `schluessel.php`
- `boats.php`

Confirm:
- no PHP or SQL errors
- 2026 licenses load with populated `lizenznummer`
- migrated Kinder values display as normal integers
- migrated mixed-note rows keep the remaining note text
- exports still download
- the key page opens and loads without schema errors
