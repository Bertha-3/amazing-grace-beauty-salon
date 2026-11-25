-- Run against the existing salon_management database. This file never creates a database.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS administrators (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  kind ENUM('product','style') NOT NULL DEFAULT 'product',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hairstyles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hairstyles_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_hairstyles_active (is_active, is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_services_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_products_active (is_active, category_id),
  FULLTEXT INDEX ft_products_search (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  hairstyle_id BIGINT UNSIGNED NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  notes TEXT NULL,
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  slot_key VARCHAR(32) GENERATED ALWAYS AS (
    CASE WHEN status = 'cancelled' THEN NULL
    ELSE CONCAT(appointment_date, ' ', TIME_FORMAT(appointment_time, '%H:%i:%s')) END
  ) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointments_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointments_hairstyle FOREIGN KEY (hairstyle_id) REFERENCES hairstyles(id) ON DELETE SET NULL,
  UNIQUE KEY uq_appointments_slot (slot_key),
  INDEX idx_appointments_date_status (appointment_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  order_number VARCHAR(32) NOT NULL UNIQUE,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(50) NOT NULL DEFAULT 'cash_on_delivery',
  delivery_address TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  INDEX idx_orders_created_status (created_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX idx_reviews_approved (is_approved, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  user_type ENUM('customer','admin') NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_email (email, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (name, kind) VALUES
('Shampoos','product'),('Conditioners','product'),('Hair oils','product'),
('Moisturisers','product'),('Hair creams','product'),('Hair masks','product'),
('Treatments','product'),('Hair accessories','product'),
('Natural twists','style'),('Natural braids','style'),('Afro & wash-and-go','style'),
('Relaxed hair styling','style');

INSERT INTO services (name, description, duration_minutes, price)
SELECT 'Wash & condition', 'A gentle cleanse, deep condition and detangling session for healthy, hydrated hair.', 60, 120.00
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Wash & condition');
INSERT INTO services (name, description, duration_minutes, price)
SELECT 'Natural styling', 'A personalised natural style using your own hair, finished with nourishing products.', 120, 250.00
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Natural styling');
INSERT INTO services (name, description, duration_minutes, price)
SELECT 'Perm & style', 'A professional retouch and style for relaxed hair, with a focus on scalp and strand care.', 150, 350.00
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Perm & style');
INSERT INTO services (name, description, duration_minutes, price)
SELECT 'Scalp & hair treatment', 'A restorative treatment tailored to your scalp and hair needs.', 75, 180.00
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Scalp & hair treatment');

INSERT INTO hairstyles (category_id,name,description,price,is_featured)
SELECT c.id,'Classic natural cornrows','Neat, scalp-friendly cornrows braided with your own hair—no added hair or extensions.',180.00,1
FROM categories c WHERE c.name='Natural braids' AND c.kind='style'
AND NOT EXISTS (SELECT 1 FROM hairstyles WHERE name='Classic natural cornrows');
INSERT INTO hairstyles (category_id,name,description,price,is_featured)
SELECT c.id,'Natural Bantu knots','A beautifully sculpted Bantu knot style made with your own hair and finished with care.',200.00,1
FROM categories c WHERE c.name='Natural twists' AND c.kind='style'
AND NOT EXISTS (SELECT 1 FROM hairstyles WHERE name='Natural Bantu knots');
INSERT INTO hairstyles (category_id,name,description,price,is_featured)
SELECT c.id,'Natural afro wash-and-go','A nourishing wash, condition and curl definition to let your natural texture shine.',160.00,1
FROM categories c WHERE c.name='Afro & wash-and-go' AND c.kind='style'
AND NOT EXISTS (SELECT 1 FROM hairstyles WHERE name='Natural afro wash-and-go');
INSERT INTO hairstyles (category_id,name,description,price,is_featured)
SELECT c.id,'Relaxed hair wrap & style','A smooth, heat-protected wrap-and-style service for relaxed hair, using your own hair—no wigs or extensions.',260.00,1
FROM categories c WHERE c.name='Relaxed hair styling' AND c.kind='style'
AND NOT EXISTS (SELECT 1 FROM hairstyles WHERE name='Relaxed hair wrap & style');

UPDATE hairstyles SET image='seed-natural-cornrows.jpg'
WHERE name='Classic natural cornrows' AND (image IS NULL OR image='');
UPDATE hairstyles SET image='seed-natural-bantu-knots.jpg'
WHERE name='Natural Bantu knots' AND (image IS NULL OR image='');
UPDATE hairstyles SET image='seed-natural-afro.jpg'
WHERE name='Natural afro wash-and-go' AND (image IS NULL OR image='');
UPDATE hairstyles SET image='seed-relaxed-hair.jpg'
WHERE name='Relaxed hair wrap & style' AND (image IS NULL OR image='');

INSERT INTO products (category_id,name,description,price,stock)
SELECT c.id,'Gentle botanical shampoo','A gentle everyday cleanse that lifts away build-up without leaving hair feeling stripped.',85.00,24
FROM categories c WHERE c.name='Shampoos' AND c.kind='product'
AND NOT EXISTS (SELECT 1 FROM products WHERE name='Gentle botanical shampoo');
INSERT INTO products (category_id,name,description,price,stock)
SELECT c.id,'Deep moisture conditioner','A rich, creamy conditioner to soften, detangle and support a healthy wash-day routine.',95.00,18
FROM categories c WHERE c.name='Conditioners' AND c.kind='product'
AND NOT EXISTS (SELECT 1 FROM products WHERE name='Deep moisture conditioner');
INSERT INTO products (category_id,name,description,price,stock)
SELECT c.id,'Nourishing hair & scalp oil','A lightweight blend for scalp massage and sealing in moisture between wash days.',70.00,30
FROM categories c WHERE c.name='Hair oils' AND c.kind='product'
AND NOT EXISTS (SELECT 1 FROM products WHERE name='Nourishing hair & scalp oil');
INSERT INTO products (category_id,name,description,price,stock)
SELECT c.id,'Satin scrunchie set','Gentle satin hair ties designed to help reduce snagging during styling and at bedtime.',45.00,20
FROM categories c WHERE c.name='Hair accessories' AND c.kind='product'
AND NOT EXISTS (SELECT 1 FROM products WHERE name='Satin scrunchie set');

INSERT INTO settings (setting_key, setting_value) VALUES
('salon_phone', '+233 20 000 0000'),
('salon_email', 'hello@amazinggracesalon.com'),
('salon_address', 'Accra, Ghana'),
('salon_hours', 'Tue–Sat, 9:00 AM–6:00 PM')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
