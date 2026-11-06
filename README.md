# K&M Urban Properties — website & management system

PHP / MySQL website for condominium rentals and property sales in Metro Manila, with an admin panel for the landing page, properties, tenants, leases, repairs and documents.

## Features

**Public website**
- Homepage built from editable sections: hero banner with search, trust highlights, featured rentals, services, featured for-sale, locations, about, numbers, testimonials, “list your property” banner, contact form
- Listings with filters (rent/sale, location, unit type, price, keyword), sorting and paging
- Property pages with photo gallery + full-screen viewer, specs, amenities, inquiry form and similar listings
- List Your Property, About Us and Contact Us pages
- Inquiry forms with spam honeypot and rate limit; optional email alert

**Admin panel** (`/admin`)
- **Landing page** — edit, hide/show and reorder each homepage section and its items (icons, services, stats, testimonials), upload section images
- **Properties** — rent or sale listings, status (available / reserved / rented / sold / inactive), featured flag, owner details, multiple photos (cover, captions, order; auto-resized), private documents
- **Tenants** — profile, IDs, emergency contact, status, documents
- **Leases & payments** — start/renew/end leases, deposits, payment records, rent statement; property and tenant statuses update automatically
- **Repairs & maintenance** — log repairs, maintenance, inspections and renovations per property (priority, status, contractor, cost, who pays) with quotes/receipts/before-after photos
- **History** — an automatic timeline for every property and every tenant (listing changes, leases, move-outs, payments, repairs, documents) plus manual notes. Each property also has a *Tenancy history* tab and a *Repairs & maintenance* log with total spent.
- **Documents** — upload PDF/Word/Excel/images to properties, tenants, leases and repair jobs. Stored outside public access and served only to logged-in admins.
- **Security** — IP whitelist / blacklist, country rule (e.g. Philippines only), failed sign-in lockouts, idle sign-out, users online with Disconnect / Ban IP, refused-visits list. Emergency off-switch: `define('SECURITY_OFF', true);` in `includes/config.php`.
- **Audit log** — every sign-in, failed sign-in and change, with user and IP; filters, CSV download, purge.
- **Theme & layout** — colour presets, 7 menu layouts, page width, light/dark mode
- **Inquiries**, **Locations**, **Site settings** (contact info, social links, logo, map), **Admin users** (administrator / staff), dashboard with expiring leases, open repairs and monthly figures

## Install

1. Create a MySQL database (phpMyAdmin or your host’s control panel).
2. Upload all files.
3. Copy `includes/config.sample.php` to `includes/config.php` and enter the database host, name, user and password. Set `BASE_URL` if the site is in a sub-folder.
4. Make sure `uploads/` and `storage/` are writable.
5. Open `https://your-site/install.php`, create the first administrator (optionally with 6 sample listings).
6. **Delete `install.php`** from the server.
7. Log in at `/admin`.

Requirements: PHP 7.4+ (8.x recommended) with PDO MySQL; GD (photo resizing) and fileinfo are recommended. MySQL 5.7+ / MariaDB 10.3+. Apache with `.htaccess` support (protects `storage/`, `includes/`, `sql/` and blocks scripts in `uploads/`). On Nginx, deny those folders in the server config.

## Folders

| Path | What |
|---|---|
| `index.php`, `properties.php`, `property.php`, … | Public pages |
| `admin/` | Admin panel |
| `includes/` | Config, shared functions, header/footer |
| `assets/` | CSS, JS, logo and default images |
| `uploads/photos`, `uploads/site` | Public images (property photos, section images) |
| `storage/documents` | Private documents |
| `sql/` | `schema.sql` (tables), `seed.sql` (default content), `sample-data.sql` (demo listings) |

## Never commit
`includes/config.php`, anything in `uploads/` and `storage/` (already in `.gitignore`).
