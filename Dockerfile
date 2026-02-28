FROM dunglas/frankenphp:php8.3

# Instalar extensões necessárias do PHP
RUN install-php-extensions pdo_mysql

# Mover php.ini de produção
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copiar o Caddyfile customizado para escutar na porta correta
COPY Caddyfile /etc/caddy/Caddyfile

# Copiar os arquivos do projeto para o diretório raiz do web server
COPY . /app/public/
