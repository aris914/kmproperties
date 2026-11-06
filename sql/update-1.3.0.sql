-- 1.3.0: test data flag (Admin › Test Data adds and clears records marked is_test = 1).
-- Applied automatically by includes/migrate.php after the deploy.

ALTER TABLE properties ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tenants ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE inquiries ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0;
