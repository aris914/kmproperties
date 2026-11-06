-- 1.2.0: owner listing submissions (approval), show/hide on website, map pin.
-- Applied automatically by includes/migrate.php on the first page load after the deploy.
-- (You can also run it by hand in phpMyAdmin; "duplicate column" errors mean it was already applied.)

ALTER TABLE properties ADD COLUMN approval ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' AFTER status;
ALTER TABLE properties ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER approval;
ALTER TABLE properties ADD COLUMN latitude DECIMAL(10,7) NULL AFTER address;
ALTER TABLE properties ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
ALTER TABLE properties ADD COLUMN show_map TINYINT(1) NOT NULL DEFAULT 1 AFTER longitude;
ALTER TABLE properties ADD COLUMN source ENUM('admin','owner') NOT NULL DEFAULT 'admin' AFTER is_featured;
ALTER TABLE properties ADD COLUMN submitted_at DATETIME NULL AFTER source;
ALTER TABLE properties ADD COLUMN submitter_ip VARCHAR(45) NULL AFTER submitted_at;
ALTER TABLE properties ADD COLUMN reviewed_at DATETIME NULL AFTER submitter_ip;
ALTER TABLE properties ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER reviewed_at;
ALTER TABLE properties ADD COLUMN review_note VARCHAR(255) NULL AFTER reviewed_by;
ALTER TABLE properties ADD KEY idx_prop_public (approval, is_visible, status);
