-- 1.5.0: rent collection — payments belong to a month, due date and grace days per lease,
-- advance applied to the first or last month, deposit / advance / due day defaults per property.
-- Applied automatically by includes/migrate.php after the deploy.

ALTER TABLE payments ADD COLUMN period_month DATE NULL AFTER paid_on;
ALTER TABLE payments MODIFY payment_type ENUM('rent','advance','deposit','deposit_applied','deposit_refund','utilities','penalty','other') NOT NULL DEFAULT 'rent';
ALTER TABLE payments ADD KEY idx_pay_period (lease_id, period_month);
UPDATE payments SET period_month = DATE_FORMAT(paid_on, '%Y-%m-01') WHERE period_month IS NULL AND payment_type = 'rent';
UPDATE payments p JOIN leases l ON l.id = p.lease_id SET p.period_month = DATE_FORMAT(l.start_date, '%Y-%m-01') WHERE p.period_month IS NULL AND p.payment_type = 'advance';
ALTER TABLE leases ADD COLUMN grace_days TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER due_day;
ALTER TABLE leases ADD COLUMN advance_applies ENUM('first','last') NOT NULL DEFAULT 'first' AFTER advance;
ALTER TABLE properties ADD COLUMN deposit_months DECIMAL(4,1) NOT NULL DEFAULT 2.0 AFTER price_period;
ALTER TABLE properties ADD COLUMN advance_months DECIMAL(4,1) NOT NULL DEFAULT 1.0 AFTER deposit_months;
ALTER TABLE properties ADD COLUMN due_day TINYINT UNSIGNED NULL AFTER advance_months;
