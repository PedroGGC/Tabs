FROM dunglas/frankenphp:php8.3

# Instalar extensões necessárias do PHP (adicionado mbstring e gd para imagens)
RUN install-php-extensions pdo_mysql mbstring gd

# Mover php.ini de produção
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configurar o Caddy para servir HTTP puro na porta 80
ENV SERVER_NAME="http://:80"

EXPOSE 80

# Definir diretório de trabalho
WORKDIR /app

# Copiar os arquivos do projeto para o container
COPY . /app/

# Ajustar permissões para os uploads de imagens (cria as pastas caso não existam)
RUN mkdir -p /app/public/uploads/covers /app/public/uploads/avatars \
    && chmod -R 777 /app/public/uploads

# Copiar arquivo de banco de dados de exemplo se o config não for versionado
RUN if [ -f /app/config/database.example.php ]; then \
    cp /app/config/database.example.php /app/config/database.php; \
    fi

# Build de frontend caso seja necessário antes de iniciar o servidor 
# (assumindo que seja interessante rodar npm run build se o package.json existir)
# RUN npm install && npm run build
