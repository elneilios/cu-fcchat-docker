FROM centos:7

# CentOS 7 is EOL, switch to vault repositories
RUN sed -i 's/mirrorlist/#mirrorlist/g' /etc/yum.repos.d/CentOS-*.repo \
    && sed -i 's|#baseurl=http://mirror.centos.org|baseurl=http://vault.centos.org|g' /etc/yum.repos.d/CentOS-*.repo

# Install Apache 2.4.6, PHP 5.4.16, and MariaDB client matching production
RUN yum -y update \
    && yum -y install \
        httpd \
        php \
        php-mysql \
        php-gd \
        php-xml \
        php-mbstring \
        php-intl \
        mariadb \
    && yum clean all

# Copy phpBB forum files to Apache document root
COPY ./phpbb /var/www/html/

# Set proper permissions
RUN chown -R apache:apache /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 /var/www/html/config.php \
    && chmod 777 /var/www/html/cache /var/www/html/files /var/www/html/store /var/www/html/images/avatars/upload

# Create PHP error log
RUN touch /var/log/php_errors.log \
    && chown apache:apache /var/log/php_errors.log \
    && chmod 664 /var/log/php_errors.log

# Configure Apache to prioritize index.php over index.html
RUN echo '<Directory /var/www/html>' > /etc/httpd/conf.d/phpbb.conf \
    && echo '    DirectoryIndex index.php index.html' >> /etc/httpd/conf.d/phpbb.conf \
    && echo '    AllowOverride All' >> /etc/httpd/conf.d/phpbb.conf \
    && echo '    Require all granted' >> /etc/httpd/conf.d/phpbb.conf \
    && echo '</Directory>' >> /etc/httpd/conf.d/phpbb.conf

# Copy and enable the custom entrypoint
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

# Use the entrypoint to handle permissions and start Apache
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/sbin/httpd", "-D", "FOREGROUND"]
