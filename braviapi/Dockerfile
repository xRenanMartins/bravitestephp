FROM shippti/php-packk:8.1.8-1

RUN cp /usr/bin/env /tmp

WORKDIR /var/www
COPY . /var/www

RUN composer install

EXPOSE 80

COPY ./entrypoint.sh /tmp
COPY ./healthcheck.sh /tmp
RUN chmod a+x /tmp/healthcheck.sh
RUN chmod +x /tmp/entrypoint.sh

HEALTHCHECK --interval=10s CMD /tmp/healthcheck.sh

RUN chmod 755 /etc/services.d/nginx/run
RUN chmod 755 /etc/services.d/php_fpm/run
