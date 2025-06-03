# Usage Guide

This guide provides detailed instructions on how to use the Laravel App Monitoring system's features.

## Table of Contents

- [Dashboard Overview](#dashboard-overview)
- [Server Management](#server-management)
- [Application Monitoring](#application-monitoring)
- [Service Monitoring](#service-monitoring)
- [Terminal Access](#terminal-access)
- [Database Operations](#database-operations)
- [Real-time Monitoring](#real-time-monitoring)

## Dashboard Overview

The dashboard provides a high-level overview of your monitored servers and applications. It displays:

- Total number of servers being monitored
- Server status summary (online, offline, warning)
- Recent application errors
- System resource usage across all servers
- Quick access to all main features

To access the dashboard, log in to the application and navigate to the Dashboard page from the main menu.

## Server Management

### Adding a Server

1. Navigate to the Servers page from the main menu
2. Click the "Add Server" button
3. Fill in the server details:
   - **Name**: A descriptive name for the server
   - **IP Address**: The server's IP address
   - **SSH Username**: The username for SSH access
   - **SSH Password**: The password for SSH access
   - **SSH Port**: The SSH port (default: 22)
4. Click "Save" to add the server

The system will automatically attempt to connect to the server and start collecting metrics.

### Viewing Server Details

1. Navigate to the Servers page
2. Click on a server name to view its details

The server details page shows:

- Server status and uptime
- CPU usage
- Memory usage
- Disk usage
- Network traffic
- Running processes
- Installed services
- Hosted applications

### Updating Server Information

1. Navigate to the server details page
2. Click the "Edit" button
3. Update the server information as needed
4. Click "Save" to apply the changes

### Removing a Server

1. Navigate to the server details page
2. Click the "Delete" button
3. Confirm the deletion when prompted

## Application Monitoring

### Adding an Application

1. Navigate to the Applications page from the main menu
2. Click the "Add Application" button
3. Fill in the application details:
   - **Name**: A descriptive name for the application
   - **Server**: Select the server where the application is hosted
   - **Path**: The full path to the application on the server
   - **Type**: The application type (Laravel, etc.)
   - **Environment File Path**: Path to the application's .env file (for Laravel apps)
4. Click "Save" to add the application

### Viewing Application Details

1. Navigate to the Applications page
2. Click on an application name to view its details

The application details page shows:

- Application status
- Error rates
- Response times
- Memory usage
- Database queries
- Cache usage
- Log entries

### Viewing Application Logs

1. Navigate to the application details page
2. Click on the "Logs" tab
3. Select the log file you want to view
4. Use the filters to narrow down log entries by:
   - Log level (info, warning, error, etc.)
   - Date range
   - Search term

### Setting Up Alerts

1. Navigate to the application details page
2. Click on the "Alerts" tab
3. Click "Add Alert"
4. Configure the alert:
   - **Metric**: The metric to monitor (error rate, response time, etc.)
   - **Condition**: The condition that triggers the alert (>, <, =, etc.)
   - **Threshold**: The value that triggers the alert
   - **Notification Method**: How you want to be notified (email, Slack, etc.)
5. Click "Save" to create the alert

## Service Monitoring

### Viewing Services

1. Navigate to the Services page from the main menu
2. View the list of services detected on your servers

The services list shows:

- Service name
- Status (running, stopped, etc.)
- Server where the service is running
- Resource usage
- Uptime

### Managing Services

1. Navigate to the Services page
2. Click on a service name to view its details
3. Use the action buttons to:
   - Start the service
   - Stop the service
   - Restart the service
   - View service logs

## Terminal Access

The terminal feature allows you to execute commands on your servers directly from the web interface.

### Accessing the Terminal

1. Navigate to the server details page
2. Click on the "Terminal" tab
3. The terminal interface will connect to the server automatically

### Executing Commands

1. Type your command in the terminal input
2. Press Enter to execute the command
3. View the command output in the terminal window

### Using Sudo Commands

For commands that require sudo privileges:

1. Use the sudo prefix in your command
2. The system will automatically handle the password authentication

Example:
```
sudo apt update
```

### Security Restrictions

For security reasons, certain dangerous commands are blocked, including:

- Commands that could delete system files
- Commands that could shut down or reboot the server
- Commands that could modify critical system configurations

If you attempt to run a blocked command, you'll receive an error message.

## Database Operations

### Creating Database Dumps

1. Navigate to the server details page
2. Click on the "Databases" tab
3. Select the database you want to dump
4. Click "Create Dump"
5. The system will create a SQL dump file and provide a download link

### Viewing Database Information

1. Navigate to the server details page
2. Click on the "Databases" tab
3. View the list of databases on the server
4. Click on a database name to view its details, including:
   - Size
   - Tables
   - Users
   - Connections

## Real-time Monitoring

The Laravel App Monitoring system provides real-time updates using WebSockets.

### Enabling Real-time Updates

Real-time updates are enabled by default when Laravel Reverb is running. To ensure real-time updates are working:

1. Make sure the Laravel Reverb service is running
2. Check that your browser supports WebSockets
3. Ensure there are no firewall restrictions blocking WebSocket connections

### Real-time Notifications

The system will display real-time notifications for:

- Server status changes
- High resource usage
- Application errors
- Service status changes

These notifications appear in the top-right corner of the interface and are also logged in the notifications center.

### Viewing the Notification Center

1. Click on the bell icon in the top navigation bar
2. View all recent notifications
3. Click on a notification to navigate to the relevant page
4. Use the filters to view specific types of notifications
