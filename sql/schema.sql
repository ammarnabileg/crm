-- All tables. UTF8MB4. Soft deletes (is_deleted=0/1).

CREATE DATABASE IF NOT EXISTS morbeh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE morbeh;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(50),
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','account_manager','writer','broker') DEFAULT 'writer',
  affiliate_code VARCHAR(50) UNIQUE,
  trust_score DECIMAL(5,2) DEFAULT 100.00,
  is_active TINYINT DEFAULT 1,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS countries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  code VARCHAR(10) UNIQUE NOT NULL,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  name_ar VARCHAR(255) NOT NULL,
  image VARCHAR(500),
  country_id INT,
  is_active TINYINT DEFAULT 1,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id)
);

CREATE TABLE IF NOT EXISTS developer_companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  name_ar VARCHAR(255),
  logo VARCHAR(500),
  is_active TINYINT DEFAULT 1,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS broker_companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  name_ar VARCHAR(255),
  logo VARCHAR(500),
  phone VARCHAR(50) NOT NULL,
  email VARCHAR(255),
  commission_rate DECIMAL(5,2) DEFAULT 0.00,
  account_manager_id INT,
  is_active TINYINT DEFAULT 1,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (account_manager_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS broker_company_staff (
  user_id INT NOT NULL,
  company_id INT NOT NULL,
  PRIMARY KEY (user_id, company_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (company_id) REFERENCES broker_companies(id)
);

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  name_ar VARCHAR(255),
  slug VARCHAR(300) UNIQUE NOT NULL,
  developer_id INT,
  city_id INT,
  location TEXT,
  sales_phone VARCHAR(50) DEFAULT '0123456789',
  min_price DECIMAL(15,2),
  min_down_payment DECIMAL(15,2),
  min_installment DECIMAL(15,2),
  min_area DECIMAL(10,2),
  description LONGTEXT,
  seo_title VARCHAR(500),
  seo_description TEXT,
  seo_keywords TEXT,
  is_published TINYINT DEFAULT 0,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (developer_id) REFERENCES developer_companies(id),
  FOREIGN KEY (city_id) REFERENCES cities(id)
);

CREATE TABLE IF NOT EXISTS project_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS project_units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(500) NOT NULL,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS articles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  slug VARCHAR(600) UNIQUE NOT NULL,
  content LONGTEXT,
  excerpt TEXT,
  cover_image VARCHAR(500),
  author_id INT NOT NULL,
  city_id INT,
  project_id INT,
  unit_ref VARCHAR(500),
  status ENUM('draft','pending','approved','rejected','needs_edit') DEFAULT 'pending',
  rejection_reason TEXT,
  seo_title VARCHAR(500),
  seo_description TEXT,
  seo_keywords TEXT,
  seo_score DECIMAL(5,2),
  readability_score DECIMAL(5,2),
  quality_score DECIMAL(5,2),
  view_count INT DEFAULT 0,
  lead_count INT DEFAULT 0,
  is_deleted TINYINT DEFAULT 0,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id),
  FOREIGN KEY (city_id) REFERENCES cities(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS article_faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  article_id INT NOT NULL,
  question VARCHAR(500) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (article_id) REFERENCES articles(id)
);

CREATE TABLE IF NOT EXISTS leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  email VARCHAR(255),
  interested_area VARCHAR(255),
  source ENUM('form','whatsapp','phone','homepage','project_page','article') DEFAULT 'form',
  status ENUM('new','assigned','in_progress','closed_won','closed_lost','duplicate') DEFAULT 'new',
  score ENUM('cold','warm','hot','high_intent') DEFAULT 'cold',
  article_id INT,
  writer_id INT,
  city_id INT,
  project_id INT,
  utm_source VARCHAR(255),
  utm_medium VARCHAR(255),
  utm_campaign VARCHAR(255),
  referrer TEXT,
  ip_address VARCHAR(100),
  device_info TEXT,
  broker_company_id INT,
  pipeline_stage ENUM('new_lead','attempted_contact','contacted','interested','viewing_scheduled','viewing_completed','negotiation','reservation','closed_won','closed_lost') DEFAULT 'new_lead',
  notes TEXT,
  is_duplicate TINYINT DEFAULT 0,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (article_id) REFERENCES articles(id),
  FOREIGN KEY (writer_id) REFERENCES users(id),
  FOREIGN KEY (city_id) REFERENCES cities(id),
  FOREIGN KEY (project_id) REFERENCES projects(id),
  FOREIGN KEY (broker_company_id) REFERENCES broker_companies(id)
);

CREATE TABLE IF NOT EXISTS lead_interactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  content TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS deals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNIQUE NOT NULL,
  broker_company_id INT NOT NULL,
  sale_amount DECIMAL(15,2) NOT NULL,
  net_profit DECIMAL(15,2) NOT NULL,
  status ENUM('pending_review','under_review','approved','rejected') DEFAULT 'pending_review',
  proof_files TEXT,
  admin_notes TEXT,
  verified_by INT,
  verified_at TIMESTAMP NULL,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  FOREIGN KEY (broker_company_id) REFERENCES broker_companies(id)
);

CREATE TABLE IF NOT EXISTS commissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNIQUE NOT NULL,
  deal_id INT UNIQUE NOT NULL,
  writer_id INT NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  rate DECIMAL(5,4) DEFAULT 0.2000,
  status ENUM('pending','under_review','approved','payable','paid','rejected') DEFAULT 'pending',
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id),
  FOREIGN KEY (deal_id) REFERENCES deals(id),
  FOREIGN KEY (writer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  writer_id INT NOT NULL,
  commission_id INT UNIQUE NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  status ENUM('pending','approved','rejected','processing','completed','frozen') DEFAULT 'pending',
  bank_details TEXT,
  admin_notes TEXT,
  processed_by INT,
  processed_at TIMESTAMP NULL,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (writer_id) REFERENCES users(id),
  FOREIGN KEY (commission_id) REFERENCES commissions(id)
);

CREATE TABLE IF NOT EXISTS complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  description TEXT NOT NULL,
  type ENUM('commission_dispute','lead_ownership','broker_behavior','content_issue','payment_issue','other') DEFAULT 'other',
  status ENUM('open','in_review','resolved','closed') DEFAULT 'open',
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  user_id INT NOT NULL,
  lead_id INT,
  resolution TEXT,
  resolved_by INT,
  resolved_at TIMESTAMP NULL,
  is_deleted TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (lead_id) REFERENCES leads(id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity VARCHAR(100) NOT NULL,
  entity_id INT,
  action VARCHAR(100) NOT NULL,
  changes JSON,
  user_id INT,
  ip_address VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) UNIQUE NOT NULL,
  value TEXT,
  `group` VARCHAR(100) DEFAULT 'general',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS global_faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(500) NOT NULL,
  answer TEXT NOT NULL,
  is_active TINYINT DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed data
INSERT IGNORE INTO countries (name, name_ar, code) VALUES ('Egypt', 'مصر', 'EG');

INSERT IGNORE INTO cities (name, name_ar, country_id) VALUES
('New Administrative Capital', 'العاصمة الإدارية الجديدة', 1),
('6th October', '6 أكتوبر', 1),
('Sheikh Zayed', 'الشيخ زايد', 1),
('New Cairo', 'القاهرة الجديدة', 1),
('Mostakbal City', 'مستقبل سيتي', 1),
('Badr City', 'مدينة بدر', 1),
('New Alamein', 'العلمين الجديدة', 1),
('New Mansoura', 'المنصورة الجديدة', 1);

INSERT IGNORE INTO settings (`key`, value, `group`) VALUES
('sales_phone', '0123456789', 'contact'),
('whatsapp_number', '0123456789', 'contact'),
('site_name', 'مربح', 'general'),
('commission_rate', '0.20', 'finance'),
('attribution_window_days', '30', 'leads');

INSERT IGNORE INTO global_faqs (question, answer, sort_order) VALUES
('ما هو رقم المبيعات؟', 'يمكنك التواصل مع إدارة المبيعات على الرقم 0123456789 لترتيب زيارة الموقع والتعرف على التفاصيل أكثر.', 1),
('هل يمكن زيارة الموقع؟', 'نعم، يمكنك التواصل مع إدارة المبيعات من خلال هذا الرقم: 0123456789 لترتيب زيارة الموقع والتعرف على التفاصيل الأكثر.', 2),
('ما هي طرق السداد المتاحة؟', 'تتوفر خيارات سداد متعددة تشمل الكاش والتقسيط على فترات مرنة. تواصل مع فريق المبيعات لمعرفة التفاصيل.', 3);

-- Admin user: password = admin123
INSERT IGNORE INTO users (name, email, password, role, affiliate_code) VALUES
('مدير النظام', 'admin@morbeh.com', '$2y$12$G1Zua3fch5qgpkcFuJCMyulASBYarkMHPpeEh6wm2lkEV6h.yeY8C', 'admin', 'ADMIN001');
