# Changelog

## 1.1.0 — 2026-11-06
- **Admin security** (Admin › Security), modelled on the RBJocsing portal:
  - IP address rules: Always allow (whitelist) and Blocked (blacklist) lists — single addresses, ranges (`/24`) or wildcards (`*`), with notes.
  - Country rule: allow only chosen countries (e.g. Philippines only) or block chosen ones; choose what happens when the country can't be found.
  - Applies to the admin panel only (recommended) or the whole site. The server refuses rules that would block your own connection.
  - Failed sign-ins lock the email (and, with 4× the tries, the IP address) for a set time; locked emails can be unlocked.
  - Inactive users are signed out after 15–90 minutes (default 45), with a one-minute "Stay signed in" warning; activity in any admin tab counts.
  - Users online with Disconnect and Ban IP; "Check an address" tool; list of recently refused visits.
  - Emergency switch: `define('SECURITY_OFF', true);` in includes/config.php.
- **Audit log** (Admin › Audit log): every sign-in, failed sign-in, lock, sign-out, file opened and change in the admin panel, with user and IP address. Filters, CSV download, purge old entries.
- **Safer sessions**: changing a password signs that account out everywhere else; disabled or deleted users are signed out at once; My account shows your sessions and recent sign-ins, with "Sign out other sessions". Security headers on admin pages.
- **Theme & layout** (Admin › Theme & layout): new admin look like the RBJocsing portal (coloured header bar, page gradient, rounded page card). 12 colour presets plus custom colours, 7 menu layouts (Classic tabs, Modern pills, Sidebar, Modern sidebar, Dark sidebar, Floating sidebar, Icon rail), page width, and light / dark / follow-device mode (each person can switch with the header button).
- Database: **run `sql/update-1.1.0.sql` once in phpMyAdmin** on an existing database (new installs get it automatically).

## 1.0.0 — 2026-11-06
- First version: public website (homepage sections, listings with filters, property pages with photo gallery, List Your Property, About, Contact).
- Admin panel: landing page sections, locations, properties with multiple photos, tenants, leases and payments, repairs & maintenance, private document uploads, inquiries, site settings, admin users.
- History timelines for every property and tenant, tenancy history per property, and repair/maintenance log with costs.
- Database: new install — run install.php.
