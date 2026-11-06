-- 1.4.0: expiry date of the tenant's valid ID. Applied automatically after the deploy.

ALTER TABLE tenants ADD COLUMN id_expiry DATE NULL AFTER id_number;
