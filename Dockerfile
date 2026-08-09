FROM php:7.2-apache

# Use Debian archive for Buster repositories
RUN sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list \
    && sed -i 's|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y \
    libmariadb-dev-compat \
    libmariadb-dev \
    mariadb-client \
    libpng-dev \
    libjpeg-dev \
    libxml2-dev \
    libzip-dev \
    libbz2-dev \
    libfreetype6-dev \
    libxpm-dev \
    libwebp-dev \
    libonig-dev \
    libmcrypt-dev \
    libldap2-dev \
    libtidy-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    libxslt1-dev \
    libsqlite3-dev \
    libgmp-dev \
    libicu-dev \
    unzip \
    openssh-server \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-xpm-dir=/usr/include/ --with-webp-dir=/usr/include/

RUN docker-php-ext-install bcmath
RUN docker-php-ext-install bz2
RUN docker-php-ext-install calendar
RUN docker-php-ext-install gd
RUN docker-php-ext-install gettext
RUN docker-php-ext-install intl
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install opcache
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install shmop
RUN docker-php-ext-install soap
RUN docker-php-ext-install sockets
RUN docker-php-ext-install sysvmsg
RUN docker-php-ext-install sysvsem
RUN docker-php-ext-install sysvshm
RUN docker-php-ext-install tidy
RUN docker-php-ext-install wddx
RUN docker-php-ext-install xsl
RUN docker-php-ext-install zip
RUN pecl install mcrypt-1.0.4 && docker-php-ext-enable mcrypt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Try installing mysqli separately to isolate build errors
# (Already included above, so this line is redundant and can be removed)

# Copy custom php.ini
COPY ./config/php.ini /usr/local/etc/php/php.ini

# Configure SSH
RUN mkdir -p /var/run/sshd \
    && sed -ri 's/^#?PermitRootLogin .*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config \
    && sed -ri 's/^#?PasswordAuthentication .*/PasswordAuthentication no/' /etc/ssh/sshd_config \
    && sed -ri 's/^UsePAM .*/UsePAM no/' /etc/ssh/sshd_config

# Copy phpBB forum files to Apache document root
COPY ./phpbb /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 /var/www/html/config.php \
    && chmod 777 /var/www/html/cache /var/www/html/files /var/www/html/store /var/www/html/images/avatars/upload

# Create PHP error log
RUN touch /var/log/php_errors.log \
    && chown www-data:www-data /var/log/php_errors.log \
    && chmod 664 /var/log/php_errors.log

# Configure Apache to prioritize index.php over index.html
RUN echo '<Directory /var/www/html>' > /etc/apache2/conf-available/phpbb.conf \
    && echo '    DirectoryIndex index.php index.html' >> /etc/apache2/conf-available/phpbb.conf \
    && echo '    AllowOverride All' >> /etc/apache2/conf-available/phpbb.conf \
    && echo '    Require all granted' >> /etc/apache2/conf-available/phpbb.conf \
    && echo '</Directory>' >> /etc/apache2/conf-available/phpbb.conf \
    && a2enconf phpbb

# Copy and enable the custom entrypoint
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 22

# Use the entrypoint to handle permissions and start Apache
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
