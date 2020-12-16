# Build: `docker build . -t civiproxy`
# Run: `docker run -d --rm -v $PWD/proxy:/var/www/html --net=host --name civiproxy civiproxy`

# This is a multi-stage build file. See https://docs.docker.com/develop/dev-best-practices/

# Generate SSL/TLS cert and key.
FROM debian:buster-slim AS cert
RUN apt update && apt install -y openssl
RUN sed -i 's/^# subjectAltName=email:copy/subjectAltName=DNS:localhost/g' /etc/ssl/openssl.cnf
RUN /usr/bin/openssl req \
-subj '/CN=localhost/O=WMF/C=UK' \
-nodes \
-new \
-x509 \
-newkey rsa:2048 \
-keyout /etc/ssl/certs/civiproxy.key \
-out /etc/ssl/certs/civiproxy.crt \
-days 1095

# Stand up CiviProxy
FROM php:7-apache
COPY --from=cert /etc/ssl/certs/ /etc/ssl/certs/
COPY proxy/ /var/www/html
COPY docker/civiproxy.ssl.conf /etc/apache2/sites-available/
RUN a2enmod ssl
RUN service apache2 restart
RUN a2dissite 000-default.conf
RUN a2dissite default-ssl.conf
RUN a2ensite civiproxy.ssl.conf

# xDebug for testing
RUN pecl install xdebug-2.9.8
RUN echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN docker-php-ext-enable xdebug
RUN service apache2 restart