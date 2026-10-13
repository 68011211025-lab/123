# BookHub — ระบบซื้อขาย/แลกเปลี่ยนหนังสือ (PHP + MySQL)

## วิธีติดตั้ง
1. เปิด Apache + MySQL (เช่น XAMPP) แล้ววางโฟลเดอร์ `book` ไว้ใน `htdocs`
2. สร้างฐานข้อมูลเปล่า (เช่น `library_lab_db`) แล้วเลือกฐานข้อมูลนั้น จากนั้น Import `setup.sql` (phpMyAdmin → Import) — สร้างตารางใหม่ทั้งหมด
3. เปิด `http://localhost/book/login.php`
4. ถ้าใช้รหัสผ่านฐานข้อมูลอื่น แก้ที่ `db.php`

## สร้างผู้ดูแลระบบ (admin)
1. สมัครสมาชิกผ่านหน้า `register.php` ด้วยรหัสผ่านของตัวเอง
2. ในฐานข้อมูลรันคำสั่ง `UPDATE users SET role = 'admin' WHERE username = 'ชื่อที่สมัคร';`
(ไม่มีบัญชีทดสอบหรือรหัสผ่านเริ่มต้นในระบบ)

## แผนที่ไฟล์
- สมาชิก: register, login, logout, profile
- ซื้อขาย: index (ค้นหา), book, create/edit/delete, my_books, favorite(s), cart
- แลกเปลี่ยน: exchange_offer, exchange, exchange_action
- ห้องสมุดส่วนตัว: library, library_edit, library_delete
- แชต/แจ้งเตือน/แนะนำ: chat, notifications, recommend
- แอดมิน: admin, admin_users, admin_books, admin_categories, admin_recommend

## การป้องกันการโจมตี
- SQL Injection: prepared statement ทุก query (ฟังก์ชัน db_*), whitelist ค่า enum, LIKE escape
- XSS: escape ทุก output ด้วย e() + Content-Security-Policy (ไม่มี inline script/style)
- CSRF: token ทุกฟอร์ม POST, ลบ/เปลี่ยนสถานะ/ออกจากระบบทำผ่าน POST เท่านั้น
- Password: password_hash/password_verify, นโยบายรหัสผ่าน, จำกัดการล็อกอินผิด (15 นาที), regenerate session id
- Session: HttpOnly, SameSite=Lax, Secure (เมื่อใช้ HTTPS), strict mode, หมดอายุเมื่อไม่ใช้งาน 2 ชม.
- Authorization/IDOR: ตรวจเจ้าของใน SQL (WHERE owner_id/user_id), แชต/แลก/คำสั่งซื้อเห็นเฉพาะผู้เกี่ยวข้อง, role อ่านจากฐานข้อมูลทุกครั้ง
- Upload: safe_upload() ตรวจเนื้อไฟล์จริง + ขนาด 2MB + ตั้งชื่อสุ่ม, ตั้งค่า Apache ใน Dockerfile ห้ามรัน PHP ในโฟลเดอร์ uploads
- อื่น ๆ: security headers, ป้องกัน open redirect, ไม่แสดง error ภายในฐานข้อมูล, transaction ตอนสั่งซื้อ/แลก
