-- setup.sql — BookHub: ระบบซื้อขาย/แลกเปลี่ยนหนังสือ + ห้องสมุดส่วนตัว + แชต
-- นำเข้าไฟล์นี้ก่อนเริ่มใช้งาน (phpMyAdmin: Import หรือ mysql < setup.sql)
-- หมายเหตุ: ไฟล์นี้ลบและสร้างตารางใหม่ทั้งหมด ข้อมูลเดิมจะหาย


SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts, recommendations, notifications, messages, library_items,
  exchanges, order_items, orders, cart_items, favorites, books, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  bio VARCHAR(300) NULL,
  is_banned TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
);

-- ประกาศหนังสือ (ขาย/แลกเปลี่ยน) มีเจ้าของ owner_id
CREATE TABLE books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  author VARCHAR(100) NOT NULL DEFAULT '',
  category_id INT NULL,
  book_condition ENUM('new','like_new','good','fair') NOT NULL DEFAULT 'good',
  purpose ENUM('sale','exchange') NOT NULL DEFAULT 'sale',
  price DECIMAL(10,2) NULL,
  description VARCHAR(1000) NULL,
  cover VARCHAR(255) NULL,
  status ENUM('available','reserved','sold','closed') NOT NULL DEFAULT 'available',
  owner_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_owner (owner_id),
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE favorites (
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, book_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

CREATE TABLE cart_items (
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, book_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  buyer_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  ship_name VARCHAR(100) NOT NULL,
  ship_phone VARCHAR(20) NOT NULL,
  ship_address VARCHAR(300) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  book_id INT NULL,
  seller_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- การแลกเปลี่ยน: pending=รอตอบรับ, accepted=กำลังแลก, completed=สำเร็จ
CREATE TABLE exchanges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  owner_id INT NOT NULL,
  target_book_id INT NOT NULL,
  offered_book_id INT NOT NULL,
  message VARCHAR(500) NULL,
  status ENUM('pending','accepted','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  owner_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  requester_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (target_book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (offered_book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- ห้องสมุดส่วนตัว
CREATE TABLE library_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  author VARCHAR(100) NOT NULL DEFAULT '',
  shelf VARCHAR(50) NULL,
  is_owned TINYINT(1) NOT NULL DEFAULT 1,
  read_status ENUM('none','want','read') NOT NULL DEFAULT 'none',
  rating TINYINT NULL,
  review VARCHAR(1000) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  body VARCHAR(1000) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_thread (book_id, sender_id, receiver_id),
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_read (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- คำแนะนำหนังสือจากแอดมิน (บรรณารักษ์)
CREATE TABLE recommendations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  author VARCHAR(100) NOT NULL DEFAULT '',
  reason VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- บันทึกความพยายามล็อกอินที่ผิด (กัน brute force)
CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip (ip, created_at),
  INDEX idx_user (username, created_at)
);

INSERT INTO categories (id, name) VALUES
  (1,'นิยาย'),(2,'วิทยาศาสตร์'),(3,'ประวัติศาสตร์'),(4,'พัฒนาตนเอง'),(5,'การ์ตูน'),(6,'ธุรกิจ');
