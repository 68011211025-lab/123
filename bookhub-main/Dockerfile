FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# ใช้ค่า production ของ PHP (ไม่แสดง error บนหน้าเว็บ)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY book/ /var/www/html/

# ลบไฟล์ที่ไม่ควรให้ใครโหลดจากเว็บ
RUN rm -f /var/www/html/setup.sql /var/www/html/README.md /var/www/html/*.html

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# ปิดการแสดงรายชื่อไฟล์, ห้ามรัน PHP ในโฟลเดอร์ uploads, ซ่อนเวอร์ชัน Apache, ห้ามโหลด .pem
RUN printf '%s\n' \
  'ServerTokens Prod' \
  'ServerSignature Off' \
  '<Directory /var/www/html>' \
  '  Options -Indexes' \
  '</Directory>' \
  '<Directory /var/www/html/uploads>' \
  '  php_admin_flag engine off' \
  '  RemoveHandler .php .phtml' \
  '</Directory>' \
  '<FilesMatch "\.pem$">' \
  '  Require all denied' \
  '</FilesMatch>' \
  > /etc/apache2/conf-available/hardening.conf \
  && a2enconf hardening
