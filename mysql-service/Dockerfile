FROM mysql:8.0

# Configure MySQL to use utf8 (3-byte) instead of utf8mb4 (4-byte)
# This makes MySQL 8.0 compatible with PHP 5.6 MySQLi extension
# which doesn't recognize charset 255 (utf8mb4)

# Create custom MySQL config
RUN echo '[mysqld]' > /etc/mysql/conf.d/charset.cnf && \
    echo '# Use utf8 (3-byte) for PHP 5.6 compatibility' >> /etc/mysql/conf.d/charset.cnf && \
    echo 'character-set-server=utf8' >> /etc/mysql/conf.d/charset.cnf && \
    echo 'collation-server=utf8_bin' >> /etc/mysql/conf.d/charset.cnf && \
    echo 'default-storage-engine=MYISAM' >> /etc/mysql/conf.d/charset.cnf && \
    echo '' >> /etc/mysql/conf.d/charset.cnf && \
    echo '[client]' >> /etc/mysql/conf.d/charset.cnf && \
    echo 'default-character-set=utf8' >> /etc/mysql/conf.d/charset.cnf && \
    echo '' >> /etc/mysql/conf.d/charset.cnf && \
    echo '[mysql]' >> /etc/mysql/conf.d/charset.cnf && \
    echo 'default-character-set=utf8' >> /etc/mysql/conf.d/charset.cnf

# Expose MySQL port
EXPOSE 3306
