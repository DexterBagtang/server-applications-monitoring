# Installation Guide

This guide provides detailed instructions for installing and configuring the Laravel App Monitoring system in different environments.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Local Development Environment](#local-development-environment)
- [Production Environment](#production-environment)
- [Docker Installation](#docker-installation)
- [Troubleshooting](#troubleshooting)

## Prerequisites

Before installing Laravel App Monitoring, ensure your system meets the following requirements:

- PHP 8.2 or higher
- Composer 2.0 or higher
- Node.js 18.0 or higher and npm
- MySQL 8.0 or compatible database
- SSH access to servers you want to monitor
- Web server (Apache or Nginx)
- SSL certificate for production environments

### PHP Extensions

The following PHP extensions are required:

- BCMath PHP Extension
- Ctype PHP Extension
- Fileinfo PHP Extension
- JSON PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- SSH2 PHP Extension (for SSH functionality)

## Local Development Environment

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/laravel-app-monitoring.git
cd laravel-app-monitoring
```

### Step 2: Install Dependencies

```bash
composer install
npm install
```

### Step 3: Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit the `.env` file to configure your database connection:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_app_monitoring
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Set Up the Database

```bash
php artisan migrate
```

If you want to seed the database with sample data:

```bash
php artisan db:seed
```

### Step 5: Build Frontend Assets

For development:

```bash
npm run dev
```

For production build:

```bash
npm run build
```

### Step 6: Start the Development Server

```bash
php artisan serve
```

For real-time updates with WebSockets:

```bash
php artisan reverb:start
```

For queue processing:

```bash
php artisan queue:work
```

Alternatively, use the combined development command:

```bash
composer dev
```

## Production Environment

### Step 1: Server Preparation

Ensure your server has all the required software installed:

- PHP 8.2+
- Composer
- Node.js and npm
- MySQL
- Web server (Nginx or Apache)
- SSL certificate

### Step 2: Clone and Configure

```bash
git clone https://github.com/yourusername/laravel-app-monitoring.git /var/www/laravel-app-monitoring
cd /var/www/laravel-app-monitoring
```

Install dependencies:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

Edit the `.env` file for production settings:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your_db_host
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120

# For WebSockets
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
```

### Step 3: Set Up the Database

```bash
php artisan migrate --force
```

### Step 4: Configure Web Server

#### Nginx Configuration Example

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name your-domain.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /var/www/laravel-app-monitoring/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration Example

Create a `.htaccess` file in the public directory:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Step 5: Set Up Supervisor for Queue Workers

Create a supervisor configuration file:

```
[program:laravel-app-monitoring-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel-app-monitoring/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/laravel-app-monitoring/storage/logs/worker.log
stopwaitsecs=3600
```

Reload supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start laravel-app-monitoring-worker:*
```

### Step 6: Set Up Cron Jobs

Add the Laravel scheduler to your crontab:

```bash
* * * * * cd /var/www/laravel-app-monitoring && php artisan schedule:run >> /dev/null 2>&1
```

### Step 7: Set Up WebSockets (Laravel Reverb)

Configure a supervisor process for Reverb:

```
[program:laravel-app-monitoring-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel-app-monitoring/artisan reverb:start
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/laravel-app-monitoring/storage/logs/reverb.log
```

Reload supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start laravel-app-monitoring-reverb:*
```

## Docker Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/laravel-app-monitoring.git
cd laravel-app-monitoring
```

### Step 2: Configure Environment

```bash
cp .env.example .env
```

Edit the `.env` file for Docker settings:

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_app_monitoring
DB_USERNAME=laravel
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Step 3: Build and Start Docker Containers

```bash
docker-compose up -d
```

### Step 4: Install Dependencies and Set Up

```bash
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app npm install
docker-compose exec app npm run build
```

### Step 5: Access the Application

The application should now be available at http://localhost:8000

## Troubleshooting

### Common Issues

#### Database Connection Issues

If you encounter database connection issues:

1. Verify your database credentials in the `.env` file
2. Ensure the database server is running
3. Check if the database exists and the user has proper permissions
4. Try running `php artisan config:clear` to clear the configuration cache

#### Permission Issues

If you encounter permission issues:

1. Ensure the web server user has write permissions to the storage and bootstrap/cache directories:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

#### WebSocket Connection Issues

If WebSocket connections are failing:

1. Verify that Laravel Reverb is running
2. Check firewall settings to ensure WebSocket ports are open
3. Ensure the Reverb configuration in `.env` is correct
4. Check the Reverb logs for any errors

#### SSH Connection Issues

If SSH connections to servers are failing:

1. Verify the server credentials
2. Ensure the PHP SSH2 extension is installed
3. Check if the server's SSH port is accessible from the application server
4. Verify that the user has sufficient permissions on the remote server
