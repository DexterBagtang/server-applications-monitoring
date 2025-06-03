# Laravel App Monitoring

A comprehensive application and server monitoring system built with Laravel and React. This application allows you to monitor multiple servers and applications, collect metrics, view logs, and execute commands remotely.

## Features

- **Server Monitoring**: Track server health, uptime, and resource usage
- **Application Monitoring**: Monitor Laravel applications, view logs, and track performance metrics
- **Service Monitoring**: Track the status of various services running on your servers
- **Terminal Access**: Secure SSH terminal access to your servers directly from the web interface
- **Database Operations**: Perform database operations like creating MySQL dumps
- **Real-time Updates**: Real-time monitoring updates using Laravel Reverb (WebSockets)
- **Secure Command Execution**: Execute commands on remote servers with built-in security measures

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL or compatible database
- SSH access to servers you want to monitor

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/laravel-app-monitoring.git
   cd laravel-app-monitoring
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install JavaScript dependencies:
   ```bash
   npm install
   ```

4. Copy the environment file and configure it:
   ```bash
   cp .env.example .env
   ```
   
5. Generate application key:
   ```bash
   php artisan key:generate
   ```

6. Configure your database in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravel_app_monitoring
   DB_USERNAME=root
   DB_PASSWORD=
   ```

7. Run database migrations:
   ```bash
   php artisan migrate
   ```

8. Build frontend assets:
   ```bash
   npm run build
   ```

9. Start the development server:
   ```bash
   php artisan serve
   ```

10. (Optional) For real-time updates, start Laravel Reverb:
    ```bash
    php artisan reverb:start
    ```

## Usage

### Adding a Server

1. Navigate to the Servers page
2. Click "Add Server"
3. Enter the server details:
   - Name
   - IP Address
   - SSH Username
   - SSH Password
   - SSH Port (default: 22)
4. Click "Save"

The system will automatically connect to the server and start collecting metrics.

### Monitoring Applications

1. Navigate to the Applications page
2. Click "Add Application"
3. Select the server where the application is hosted
4. Enter the application details:
   - Name
   - Path to the application
   - Type (Laravel, etc.)
5. Click "Save"

The system will start monitoring the application and collecting metrics.

### Executing Commands

1. Navigate to the Server detail page
2. Use the terminal interface to execute commands
3. For sudo commands, use the provided sudo helper

Note: The system has built-in security measures to prevent dangerous commands from being executed.

## Security

This application includes several security features:

- Command filtering to prevent dangerous operations
- Authentication and authorization for all sensitive operations
- Secure storage of server credentials
- SSH connection management with proper cleanup

## Development

### Running in Development Mode

```bash
composer dev
```

This will start the Laravel server, queue worker, and Vite development server concurrently.

### Running with SSR (Server-Side Rendering)

```bash
composer dev:ssr
```

This will build the SSR bundle and start all necessary services including Inertia SSR.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
