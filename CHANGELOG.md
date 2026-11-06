# Changelog

## 1.5.0 — 2026-11-06
- **Rent Collection** (new menu item, with a badge for tenants who are overdue): pick a month and see every lease with its due date, rent, paid, balance and status (Paid / Due / Overdue / Upcoming), totals and collection rate, plus all unpaid rent from earlier months. "Record payment" opens the lease with the month and balance filled in; "Send reminder" opens a text message, Viber or email with a ready-made reminder (or copies it). CSV and print.
- **Monthly due date**: each lease has "Rent is due every [day] of the month" and a grace period (days before rent counts as overdue). Each property can set a default due day for new leases.
- **Rent ledger on every lease**: a month-by-month schedule with due date, paid, balance and status, a "Pay" button on unpaid months, and totals (due so far, paid, balance, months overdue, next due).
- **Payments belong to a month**: pick the month (or pay several months at once — the amount is split). New payment types: deposit applied to rent, deposit refund. Existing rent payments were assigned to the month they were paid in.
- **Deposit & advance per property and per lease**: properties have deposit and advance in months (shown on the website as "1 month advance · 2 months deposit") which fill in new leases. Leases track deposit required / received / applied / refunded / held, and advance required / received; the advance pays the first or the last month(s). New leases can record the deposit and advance as received. Ending a lease can use the deposit for unpaid rent first and records the refund.
- **Reports** (new menu item): rent collection by month (with chart), unpaid rent aging, income & expenses by property, payments register, deposits & advances, occupancy & rent roll, lease expiry, repair costs — each with filters, totals, CSV download and print.
- Dashboard: this month's rent collected vs due and overdue rent. Leases list shows each lease's balance.
- Database: applied automatically after the deploy.

## 1.4.0 — 2026-11-06
- **Tenant ID photos and documents on the tenant form** (Add / Edit tenant):
  - New **Valid ID** card: ID type, number, **expiry date**, and **Front** and **Back** photo slots. "Take photo" opens the phone camera; "Upload" takes an image or PDF. Photos are made smaller before upload, preview right away, and a new photo replaces the old one on save. Remove buttons per side.
  - New **Other documents** card: upload several files at once with a category (proof of income, certificate of employment, payslip, bank statement, NBI / police or barangay clearance, application form, contract, other) and an optional title; see, download or delete existing ones.
  - Everything is saved privately (admins only), added to the tenant's history and the audit log.
- Tenant page shows the ID photos with an expiry badge (expired / expiring within 60 days) and an "Update ID photos" button; the Documents tab links to the ID upload.
- Test Data now creates ID card images (front and back) and certificates of employment for test tenants.
- Database: adds the ID expiry date (applied automatically after the deploy).

## 1.3.1 — 2026-11-06
- Fix: browsers kept showing the old logo with a navy box after 1.3.0; the logo now reloads with each version. No database update.

## 1.3.0 — 2026-11-06
- **Theme colours on the public website**: Admin › Theme & Layout now also colours the website (header, hero, buttons, prices, badges, footer). Switch "Use these colours on the website" off to keep the original K&M navy, teal and gold. Text on highlight buttons switches between dark and light automatically. The white logo now has a transparent background so it fits any header colour.
- **Admin › Test Data** (administrators only): add made-up records for a full workflow — properties for rent and sale with generated photos and map pins, tenants, leases (re-let unit with a former tenant, lease ending soon, upcoming lease, rent behind), payments, repairs in every status with costs and receipts, sample PDF documents, owner submissions (waiting and rejected), inquiries and history. Shows totals vs test records, and clears only test data or, after typing DELETE, all records. Test records show a TEST badge and codes starting with TEST-.
- Database: adds an is_test flag (applied automatically after the deploy).

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
