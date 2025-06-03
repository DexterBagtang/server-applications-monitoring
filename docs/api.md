# API Documentation

This document provides information about the Laravel App Monitoring API endpoints, which allow you to programmatically interact with the monitoring system.

## Authentication

All API requests require authentication using Laravel Sanctum. You need to include a valid API token in the request headers.

### Obtaining an API Token

API tokens can be generated through the web interface:

1. Log in to the Laravel App Monitoring application
2. Navigate to Settings > API Tokens
3. Click "Create New Token"
4. Provide a name for the token and select the appropriate permissions
5. Copy the generated token (it will only be shown once)

### Using the API Token

Include the token in the `Authorization` header of your requests:

```
Authorization: Bearer YOUR_API_TOKEN
```

## API Endpoints

### Servers

#### List All Servers

```
GET /api/servers
```

Returns a list of all servers being monitored.

**Response Example:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Production Web Server",
      "ip_address": "192.168.1.100",
      "status": "Online",
      "uptime": "10 days, 4 hours",
      "cpu_usage": 24.5,
      "memory_usage": 68.2,
      "disk_usage": 42.7,
      "created_at": "2025-04-15T10:30:00Z",
      "updated_at": "2025-05-30T15:45:22Z"
    },
    {
      "id": 2,
      "name": "Database Server",
      "ip_address": "192.168.1.101",
      "status": "Online",
      "uptime": "15 days, 2 hours",
      "cpu_usage": 35.8,
      "memory_usage": 72.1,
      "disk_usage": 58.3,
      "created_at": "2025-04-15T10:35:00Z",
      "updated_at": "2025-05-30T15:45:24Z"
    }
  ]
}
```

#### Get Server Details

```
GET /api/servers/{server_id}
```

Returns detailed information about a specific server.

**Response Example:**

```json
{
  "data": {
    "id": 1,
    "name": "Production Web Server",
    "ip_address": "192.168.1.100",
    "status": "Online",
    "uptime": "10 days, 4 hours",
    "cpu_usage": 24.5,
    "memory_usage": 68.2,
    "disk_usage": 42.7,
    "network": {
      "rx_bytes": 1024000,
      "tx_bytes": 512000
    },
    "services": [
      {
        "id": 1,
        "name": "nginx",
        "status": "running"
      },
      {
        "id": 2,
        "name": "mysql",
        "status": "running"
      }
    ],
    "applications": [
      {
        "id": 1,
        "name": "E-commerce Website",
        "status": "healthy"
      }
    ],
    "created_at": "2025-04-15T10:30:00Z",
    "updated_at": "2025-05-30T15:45:22Z"
  }
}
```

#### Create a Server

```
POST /api/servers
```

Creates a new server to monitor.

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | A descriptive name for the server |
| ip_address | string | Yes | The server's IP address |
| username | string | Yes | SSH username |
| password | string | Yes | SSH password |
| port | integer | No | SSH port (default: 22) |
| is_active | boolean | No | Whether the server is active (default: true) |

**Response Example:**

```json
{
  "data": {
    "id": 3,
    "name": "New Test Server",
    "ip_address": "192.168.1.102",
    "status": "Fetching",
    "created_at": "2025-05-30T16:00:00Z",
    "updated_at": "2025-05-30T16:00:00Z"
  },
  "message": "Server created successfully"
}
```

#### Update a Server

```
PUT /api/servers/{server_id}
```

Updates an existing server.

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | No | A descriptive name for the server |
| ip_address | string | No | The server's IP address |
| username | string | No | SSH username |
| password | string | No | SSH password (leave empty to keep current) |
| port | integer | No | SSH port |
| is_active | boolean | No | Whether the server is active |

**Response Example:**

```json
{
  "data": {
    "id": 1,
    "name": "Updated Server Name",
    "ip_address": "192.168.1.100",
    "status": "Fetching",
    "updated_at": "2025-05-30T16:15:00Z"
  },
  "message": "Server updated successfully"
}
```

#### Delete a Server

```
DELETE /api/servers/{server_id}
```

Removes a server from monitoring.

**Response Example:**

```json
{
  "message": "Server deleted successfully"
}
```

#### Refresh Server Metrics

```
POST /api/servers/{server_id}/refresh
```

Triggers a refresh of the server's metrics.

**Response Example:**

```json
{
  "message": "Server metrics refresh initiated"
}
```

#### Execute Command on Server

```
POST /api/servers/{server_id}/execute
```

Executes a command on the server.

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| command | string | Yes | The command to execute |
| sudo_enabled | boolean | No | Whether to use sudo (default: false) |

**Response Example:**

```json
{
  "output": "Linux server 5.15.0-58-generic #64-Ubuntu SMP x86_64 GNU/Linux"
}
```

### Applications

#### List All Applications

```
GET /api/applications
```

Returns a list of all applications being monitored.

**Response Example:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "E-commerce Website",
      "server_id": 1,
      "server_name": "Production Web Server",
      "status": "healthy",
      "type": "Laravel",
      "path": "/var/www/ecommerce",
      "created_at": "2025-04-16T09:30:00Z",
      "updated_at": "2025-05-30T15:45:30Z"
    },
    {
      "id": 2,
      "name": "Admin Dashboard",
      "server_id": 1,
      "server_name": "Production Web Server",
      "status": "warning",
      "type": "Laravel",
      "path": "/var/www/admin",
      "created_at": "2025-04-16T09:35:00Z",
      "updated_at": "2025-05-30T15:45:32Z"
    }
  ]
}
```

#### Get Application Details

```
GET /api/applications/{application_id}
```

Returns detailed information about a specific application.

**Response Example:**

```json
{
  "data": {
    "id": 1,
    "name": "E-commerce Website",
    "server_id": 1,
    "server_name": "Production Web Server",
    "status": "healthy",
    "type": "Laravel",
    "path": "/var/www/ecommerce",
    "metrics": {
      "error_rate": 0.2,
      "response_time": 245,
      "memory_usage": 128.5,
      "database_queries": 1250,
      "cache_hits": 8500,
      "cache_misses": 150
    },
    "created_at": "2025-04-16T09:30:00Z",
    "updated_at": "2025-05-30T15:45:30Z"
  }
}
```

#### Create an Application

```
POST /api/applications
```

Creates a new application to monitor.

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | A descriptive name for the application |
| server_id | integer | Yes | The ID of the server hosting the application |
| path | string | Yes | Full path to the application on the server |
| type | string | Yes | Application type (Laravel, etc.) |
| env_path | string | No | Path to the environment file (for Laravel apps) |

**Response Example:**

```json
{
  "data": {
    "id": 3,
    "name": "New Application",
    "server_id": 1,
    "server_name": "Production Web Server",
    "status": "pending",
    "type": "Laravel",
    "path": "/var/www/newapp",
    "created_at": "2025-05-30T16:30:00Z",
    "updated_at": "2025-05-30T16:30:00Z"
  },
  "message": "Application created successfully"
}
```

#### Update an Application

```
PUT /api/applications/{application_id}
```

Updates an existing application.

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | No | A descriptive name for the application |
| server_id | integer | No | The ID of the server hosting the application |
| path | string | No | Full path to the application on the server |
| type | string | No | Application type (Laravel, etc.) |
| env_path | string | No | Path to the environment file (for Laravel apps) |

**Response Example:**

```json
{
  "data": {
    "id": 1,
    "name": "Updated Application Name",
    "server_id": 1,
    "server_name": "Production Web Server",
    "status": "pending",
    "type": "Laravel",
    "path": "/var/www/ecommerce",
    "updated_at": "2025-05-30T16:45:00Z"
  },
  "message": "Application updated successfully"
}
```

#### Delete an Application

```
DELETE /api/applications/{application_id}
```

Removes an application from monitoring.

**Response Example:**

```json
{
  "message": "Application deleted successfully"
}
```

#### Fetch Application Logs

```
GET /api/applications/{application_id}/logs
```

Fetches the logs for a specific application.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| level | string | Filter by log level (info, warning, error, etc.) |
| start_date | string | Start date for log entries (YYYY-MM-DD) |
| end_date | string | End date for log entries (YYYY-MM-DD) |
| search | string | Search term to filter log entries |
| limit | integer | Maximum number of log entries to return (default: 100) |

**Response Example:**

```json
{
  "data": [
    {
      "timestamp": "2025-05-30T15:30:22Z",
      "level": "error",
      "message": "Undefined variable: user",
      "context": {
        "file": "/var/www/ecommerce/app/Http/Controllers/UserController.php",
        "line": 45
      }
    },
    {
      "timestamp": "2025-05-30T15:28:15Z",
      "level": "info",
      "message": "User logged in",
      "context": {
        "user_id": 123
      }
    }
  ],
  "meta": {
    "total": 245,
    "per_page": 100,
    "current_page": 1,
    "last_page": 3
  }
}
```

### Services

#### List All Services

```
GET /api/services
```

Returns a list of all services being monitored.

**Response Example:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "nginx",
      "server_id": 1,
      "server_name": "Production Web Server",
      "status": "running",
      "uptime": "10 days, 2 hours",
      "created_at": "2025-04-16T10:00:00Z",
      "updated_at": "2025-05-30T15:45:40Z"
    },
    {
      "id": 2,
      "name": "mysql",
      "server_id": 1,
      "server_name": "Production Web Server",
      "status": "running",
      "uptime": "10 days, 2 hours",
      "created_at": "2025-04-16T10:05:00Z",
      "updated_at": "2025-05-30T15:45:42Z"
    }
  ]
}
```

#### Get Service Details

```
GET /api/services/{service_id}
```

Returns detailed information about a specific service.

**Response Example:**

```json
{
  "data": {
    "id": 1,
    "name": "nginx",
    "server_id": 1,
    "server_name": "Production Web Server",
    "status": "running",
    "uptime": "10 days, 2 hours",
    "cpu_usage": 2.5,
    "memory_usage": 128.4,
    "ports": [80, 443],
    "created_at": "2025-04-16T10:00:00Z",
    "updated_at": "2025-05-30T15:45:40Z"
  }
}
```

#### Control a Service

```
POST /api/services/{service_id}/control
```

Controls a service (start, stop, restart).

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | The action to perform (start, stop, restart) |

**Response Example:**

```json
{
  "message": "Service nginx restarted successfully"
}
```

#### Fetch Service Logs

```
GET /api/services/{service_id}/logs
```

Fetches the logs for a specific service.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| limit | integer | Maximum number of log entries to return (default: 100) |

**Response Example:**

```json
{
  "data": [
    {
      "timestamp": "2025-05-30T15:40:22Z",
      "message": "2025/05/30 15:40:22 [notice] 1234#0: signal process started"
    },
    {
      "timestamp": "2025-05-30T15:40:21Z",
      "message": "2025/05/30 15:40:21 [notice] 1234#0: signal 15 (SIGTERM) received, exiting"
    }
  ]
}
```

## Error Handling

The API uses standard HTTP status codes to indicate the success or failure of requests:

- `200 OK`: The request was successful
- `201 Created`: A resource was successfully created
- `400 Bad Request`: The request was invalid or cannot be served
- `401 Unauthorized`: Authentication is required or failed
- `403 Forbidden`: The authenticated user doesn't have permission
- `404 Not Found`: The requested resource doesn't exist
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: An error occurred on the server

Error responses include a message explaining what went wrong:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "ip_address": [
      "The ip address must be a valid IP address."
    ]
  }
}
```

## Rate Limiting

API requests are subject to rate limiting to prevent abuse. The current limits are:

- 60 requests per minute for authenticated users
- 5 requests per minute for unauthenticated requests

Rate limit information is included in the response headers:

- `X-RateLimit-Limit`: The maximum number of requests allowed per time window
- `X-RateLimit-Remaining`: The number of requests remaining in the current time window
- `X-RateLimit-Reset`: The time at which the current rate limit window resets (Unix timestamp)

When the rate limit is exceeded, the API returns a `429 Too Many Requests` response.

## Pagination

List endpoints support pagination through query parameters:

- `page`: The page number to retrieve (default: 1)
- `per_page`: The number of items per page (default: 15, max: 100)

Paginated responses include metadata about the pagination:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Example Resource 1"
    },
    {
      "id": 2,
      "name": "Example Resource 2"
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "path": "https://example.com/api/resources",
    "per_page": 15,
    "to": 15,
    "total": 45
  },
  "links": {
    "first": "https://example.com/api/resources?page=1",
    "last": "https://example.com/api/resources?page=3",
    "prev": null,
    "next": "https://example.com/api/resources?page=2"
  }
}
```
