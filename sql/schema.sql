-- K&M Urban Properties — database schema (MySQL 5.7+ / MariaDB 10.3+)
-- install.php runs this file automatically. You can also import it in phpMyAdmin.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Landing page sections (one row per section, admin can edit / reorder / hide)
CREATE TABLE IF NOT EXISTS landing_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_key VARCHAR(40) NOT NULL UNIQUE,
  label VARCHAR(80) NOT NULL,
  title VARCHAR(200) NULL,
  highlight VARCHAR(200) NULL,
  subtitle VARCHAR(255) NULL,
  body TEXT NULL,
  image VARCHAR(255) NULL,
  button_text VARCHAR(80) NULL,
  button_link VARCHAR(255) NULL,
  item_limit INT UNSIGNED NOT NULL DEFAULT 6,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repeating items inside a section (features, services, stats, testimonials)
CREATE TABLE IF NOT EXISTS section_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id INT UNSIGNED NOT NULL,
  icon VARCHAR(60) NULL,
  title VARCHAR(200) NULL,
  subtitle VARCHAR(200) NULL,
  text TEXT NULL,
  image VARCHAR(255) NULL,
  link VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_items_section FOREIGN KEY (section_id) REFERENCES landing_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  listing_type ENUM('rent','sale') NOT NULL DEFAULT 'rent',
  property_type VARCHAR(40) NOT NULL DEFAULT 'Condominium',
  unit_type VARCHAR(40) NULL,
  location_id INT UNSIGNED NULL,
  building VARCHAR(150) NULL,
  unit_no VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  show_map TINYINT(1) NOT NULL DEFAULT 1,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  price_period VARCHAR(20) NULL,
  deposit_months DECIMAL(4,1) NOT NULL DEFAULT 2.0,
  advance_months DECIMAL(4,1) NOT NULL DEFAULT 1.0,
  due_day TINYINT UNSIGNED NULL,
  bedrooms TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bathrooms TINYINT UNSIGNED NOT NULL DEFAULT 1,
  floor_area DECIMAL(8,2) NULL,
  floor_level VARCHAR(20) NULL,
  parking TINYINT UNSIGNED NOT NULL DEFAULT 0,
  furnishing ENUM('Unfurnished','Semi-furnished','Fully furnished') NOT NULL DEFAULT 'Unfurnished',
  description TEXT NULL,
  amenities TEXT NULL,
  status ENUM('available','reserved','rented','sold','inactive') NOT NULL DEFAULT 'available',
  approval ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved',
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  source ENUM('admin','owner') NOT NULL DEFAULT 'admin',
  submitted_at DATETIME NULL,
  submitter_ip VARCHAR(45) NULL,
  reviewed_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  review_note VARCHAR(255) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  owner_name VARCHAR(150) NULL,
  owner_phone VARCHAR(60) NULL,
  owner_email VARCHAR(190) NULL,
  internal_notes TEXT NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_prop_listing (listing_type, status),
  KEY idx_prop_location (location_id),
  KEY idx_prop_public (approval, is_visible, status),
  CONSTRAINT fk_prop_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NOT NULL,
  file VARCHAR(255) NOT NULL,
  caption VARCHAR(200) NULL,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_photo_prop (property_id),
  CONSTRAINT fk_photo_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  birth_date DATE NULL,
  occupation VARCHAR(120) NULL,
  employer VARCHAR(150) NULL,
  id_type VARCHAR(60) NULL,
  id_number VARCHAR(80) NULL,
  id_expiry DATE NULL,
  address VARCHAR(255) NULL,
  emergency_name VARCHAR(120) NULL,
  emergency_phone VARCHAR(60) NULL,
  emergency_relation VARCHAR(60) NULL,
  status ENUM('prospect','active','former','blacklisted') NOT NULL DEFAULT 'prospect',
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tenant_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A lease links a tenant to a property for a period (this is the tenancy history)
CREATE TABLE IF NOT EXISTS leases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  move_out_date DATE NULL,
  monthly_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
  deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
  advance DECIMAL(12,2) NOT NULL DEFAULT 0,
  advance_applies ENUM('first','last') NOT NULL DEFAULT 'first',
  due_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
  grace_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('active','ended','terminated','upcoming') NOT NULL DEFAULT 'active',
  deposit_refunded DECIMAL(12,2) NULL,
  end_reason VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lease_prop (property_id),
  KEY idx_lease_tenant (tenant_id),
  CONSTRAINT fk_lease_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_lease_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lease_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_on DATE NOT NULL,
  period_month DATE NULL,
  period_label VARCHAR(40) NULL,
  payment_type ENUM('rent','advance','deposit','deposit_applied','deposit_refund','utilities','penalty','other') NOT NULL DEFAULT 'rent',
  method VARCHAR(40) NULL,
  reference VARCHAR(100) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pay_lease (lease_id),
  KEY idx_pay_period (lease_id, period_month),
  CONSTRAINT fk_pay_lease FOREIGN KEY (lease_id) REFERENCES leases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repairs, maintenance works and inspections per property
CREATE TABLE IF NOT EXISTS maintenance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  category ENUM('repair','maintenance','inspection','renovation','cleaning','other') NOT NULL DEFAULT 'repair',
  area VARCHAR(80) NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  reported_by VARCHAR(120) NULL,
  reported_on DATE NOT NULL,
  scheduled_on DATE NULL,
  completed_on DATE NULL,
  contractor VARCHAR(150) NULL,
  contractor_phone VARCHAR(60) NULL,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  charged_to ENUM('owner','tenant','company','warranty') NOT NULL DEFAULT 'owner',
  description TEXT NULL,
  resolution TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_maint_prop (property_id),
  KEY idx_maint_tenant (tenant_id),
  KEY idx_maint_status (status),
  CONSTRAINT fk_maint_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_maint_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded documents for properties, tenants, leases and maintenance jobs (stored privately)
CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('property','tenant','lease','maintenance') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  category VARCHAR(60) NULL,
  file VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NULL,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_doc_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timeline for properties and tenants (automatic events + manual notes)
CREATE TABLE IF NOT EXISTS history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NULL,
  tenant_id INT UNSIGNED NULL,
  event_type VARCHAR(40) NOT NULL,
  summary VARCHAR(255) NOT NULL,
  details TEXT NULL,
  event_date DATE NOT NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist_prop (property_id, event_date),
  KEY idx_hist_tenant (tenant_id, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquiry_type ENUM('property','listing','contact') NOT NULL DEFAULT 'contact',
  property_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  message TEXT NULL,
  extra TEXT NULL,
  status ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
  admin_notes TEXT NULL,
  ip VARCHAR(45) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inq_status (status),
  CONSTRAINT fk_inq_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Security (1.1.0) ----------
CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT UNSIGNED NULL,
  user_email VARCHAR(190) NULL,
  action VARCHAR(20) NOT NULL,
  area VARCHAR(40) NOT NULL,
  record VARCHAR(255) NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  KEY idx_audit_at (at),
  KEY idx_audit_area (area, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  k VARCHAR(200) PRIMARY KEY,
  fails INT UNSIGNED NOT NULL DEFAULT 0,
  last_at DATETIME NOT NULL,
  locked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_blocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(45) NOT NULL,
  cc CHAR(2) NULL,
  reason VARCHAR(200) NULL,
  path VARCHAR(120) NULL,
  KEY idx_blocks_ip (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_geo (
  ip VARCHAR(45) PRIMARY KEY,
  cc CHAR(2) NULL,
  country VARCHAR(120) NULL,
  place VARCHAR(120) NULL,
  isp VARCHAR(120) NULL,
  fetched_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  session_key CHAR(64) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  signed_in_at DATETIME NOT NULL,
  last_seen DATETIME NOT NULL,
  kicked_at DATETIME NULL,
  KEY idx_sess_user (user_id),
  KEY idx_sess_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Calendar (1.6.0) ----------
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
