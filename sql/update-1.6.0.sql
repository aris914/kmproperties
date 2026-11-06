-- 1.6.0: calendar events (meetings, viewings, inspections …) with tenants, owners or buyers.
-- Applied automatically by includes/migrate.php after the deploy.

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  event_type ENUM('viewing','meeting','inspection','signing','move_in','move_out','collection','other') NOT NULL DEFAULT 'meeting',
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  all_day TINYINT(1) NOT NULL DEFAULT 0,
  location VARCHAR(255) NULL,
  property_id INT UNSIGNED NULL,
  tenant_id INT UNSIGNED NULL,
  inquiry_id INT UNSIGNED NULL,
  with_type ENUM('tenant','owner','buyer','other') NOT NULL DEFAULT 'other',
  contact_name VARCHAR(150) NULL,
  contact_phone VARCHAR(60) NULL,
  contact_email VARCHAR(190) NULL,
  notes TEXT NULL,
  status ENUM('scheduled','done','cancelled') NOT NULL DEFAULT 'scheduled',
  created_by INT UNSIGNED NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_events_start (start_at),
  KEY idx_events_prop (property_id),
  KEY idx_events_tenant (tenant_id),
  CONSTRAINT fk_events_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
