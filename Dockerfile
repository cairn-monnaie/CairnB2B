FROM php:7.1-fpm


LABEL description="CairnB2B based on debian" \
        maintainer="mazda91 <https://github.com/mazda91>"

RUN apt-get update \
    && apt-get install -y vim curl \
    && apt-get install -y python3.5 python3-pip \
    && pip3 install python-slugify PyYAML datetime requests \
    && docker-php-ext-install pdo pdo_mysql 

COPY . /var/www/Symfony
RUN cd /var/www && php -r "eval('?>'.file_get_contents('http://getcomposer.org/installer'));" \  
    && chown -R www-data:www-data /var/www/Symfony

WORKDIR /var/www/Symfony

EXPOSE 9000

CMD ["php-fpm"]