FROM php:8.2-apache

RUN apt-get update && apt-get install -y python3 python3-venv

RUN docker-php-ext-install pdo_mysql mysqli

RUN python3 -m venv /opt/venv

ENV PATH="/opt/venv/bin:$PATH"

COPY requirements.txt /app/requirements.txt

RUN pip install -r /app/requirements.txt

EXPOSE 80

COPY . /var/www/html/
