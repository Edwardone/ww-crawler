FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
	curl \
	wget \
	unzip \
	libfreetype6-dev \
	libjpeg62-turbo-dev \
	libpng-dev \
    libcurl4-openssl-dev \
    libpcre3-dev \
    libssl-dev \
	libxslt-dev \
	libicu-dev \
	libmcrypt-dev \
	libxml2-dev \
	libzip-dev \
	libonig-dev \
	libpq-dev \
    libgd-dev \
	poppler-utils \
	ffmpeg \
	sox

RUN docker-php-ext-install -j$(nproc) iconv mbstring mysqli pdo_mysql zip calendar
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl exif zip pcntl ftp

RUN docker-php-ext-enable pcntl ftp

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ADD ./docker/conf/php/php.ini /usr/local/etc/php/php.ini

RUN pecl install redis
RUN docker-php-ext-enable redis
