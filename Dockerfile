# ChatDesk — Docker image (PHP 8 + Apache)
# Build:  docker build -t ghcr.io/ittipolint/chatdesk:latest .
FROM php:8.2-apache

# ติดตั้ง PHP extensions ที่เว็บจำเป็นต้องใช้
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring fileinfo curl \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# คัปปี้ซอร์สโค้ดทั้งหมดไปยัง docroot
COPY . /var/www/html/

# สร้าง config.php จาก config.sample.php (ค่าใน sample อ่านจาก env ตอน run)
RUN if [ ! -f config.php ]; then cp config.sample.php config.php; fi \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# เปิด AllowOverride ให้ .htaccess ทำงาน (config.php, inc/ จะถูกกันไม่ให้เข้า)
COPY docker/apache-chatdesk.conf /etc/apache2/conf-available/chatdesk.conf
RUN a2enconf chatdesk

EXPOSE 80

CMD ["apache2-foreground"]