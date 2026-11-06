# Working on kmproperties with Claude

Plain PHP 7.4+/MySQL site (no framework, no build step). Public pages in the root, admin panel in `admin/`, shared code in `includes/bootstrap.php`.

## Commit and push workflow
- Claude commits and pushes **directly with git**. Remote: `origin` = `https://github.com/aris914/kmproperties.git`, branch `main`.
- **Every push to `main` deploys the live site**: `.github/workflows/deploy.yml` uploads by FTP to InfinityFree (kmproperties.page.gd, folder `/kmproperties.page.gd/htdocs/`, account if0_43311913). A push means the change is live within a minute or two.
- So: test first, then ask **"Commit and push?"** and push **only after the user confirms**.
- Before committing: `git fetch` and make sure `main` is not behind `origin/main`; if it is, `git pull --rebase` first.
- Commit messages: short title, then what changed and why, ending with the Co-Authored-By line the session asks for.

## Release conventions
- Raise `APP_VERSION` in `includes/bootstrap.php` (new features = MINOR, fixes = PATCH). CSS/JS are loaded with `?v=APP_VERSION`, so raising it busts browser caches.
- Add an entry at the top of `CHANGELOG.md` (`## x.y.z — YYYY-MM-DD`, plain-language bullets, say whether a database update is needed).
- Database changes: add them to `sql/schema.sql` (new installs) **and** to a new `sql/update-x.y.z.sql`, then raise `DB_VERSION` in `includes/bootstrap.php`. `includes/migrate.php` applies newer update files automatically on the first request after the deploy (statements that already ran are skipped), so write update files as plain CREATE/ALTER statements, one per line ending in `;`.

## Never commit
- `includes/config.php` (server database login), anything in `uploads/photos`, `uploads/site`, `storage/documents`. All are in `.gitignore` and excluded from the FTP deploy.

## Testing
- No PHP/MySQL on this Windows machine. Syntax-check every PHP file before committing (php-parser via Node in the scratchpad), and say plainly what was and wasn't tested on a real server.
