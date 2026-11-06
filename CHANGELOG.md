# Changelog

## 1.2.0 — 2026-11-06
- **List Your Property** now creates a real listing: owners add details, amenities, up to 12 photos (made smaller in the browser so they upload quickly) and a **map pin** (search the building, use their location, or click the map). Spam protection: hidden bot field and 3 submissions per hour.
- Submissions wait for approval and stay hidden. New **Admin › Owner Submissions** (with a badge for pending ones): review, edit, preview, then **Approve & publish**, **Approve, keep hidden**, or **Reject** with a reason. The dashboard shows a notice when submissions are waiting. Each submission is also saved as an inquiry.
- **Show / hide on website** for every property: a Shown / Hidden button on the property list and property page, a switch on the edit form, and a "Website" filter. Hidden listings keep working for leases, repairs and documents. Signed-in admins can preview hidden listings on the website.
- **Map pin** on every property (admin edit form), with a map and "Open in Google Maps" on the property page (can be turned off per property).
- Database updates now apply **automatically** on the first page load after a deploy (this also applies the 1.1.0 security tables) — no phpMyAdmin step needed.

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
