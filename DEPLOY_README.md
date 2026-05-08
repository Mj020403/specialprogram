# HARVEST deploy package

## Cleaned items
- Removed duplicate `project/harvest` copy.
- Kept only one database connection file: `database/database.php`.
- Simplified `app/config/database.php` to only load the main database file.
- Moved the single SQL dump to `database/harvest.sql`.
- Removed loose root SQL migration files from the deploy package.

## Before deploy
Edit `database/database.php` and change:
- `$user`
- `$password`
- `$dbname`

## Deploy steps
1. Upload this folder as **harvest**.
2. Import `database/harvest.sql` into MySQL.
3. Update database credentials in `database/database.php`.
4. Make sure Apache mod_rewrite is enabled.
5. Open `/harvest/` in the browser.

## Notes
This package was cleaned for structure and syntax-checked, but full runtime testing still requires your real MySQL server and hosting environment.
