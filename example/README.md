# Piri Router Example

This is a complete example application demonstrating the features of Piri Router.

## Installation

1. Clone the repository
2. Navigate to the example directory:
```bash
cd example
```

3. Install dependencies:
```bash
composer install
```

This will automatically symlink the main Piri Router package from the parent directory.

## Running the Example

### Using PHP's Built-in Server

1. Start the PHP development server:
```bash
cd public
php -S localhost:8000
```

2. Visit http://localhost:8000 in your browser

### Using Apache

1. Make sure mod_rewrite is enabled:
```bash
sudo a2enmod rewrite
sudo service apache2 restart  # On Ubuntu/Debian
```

2. Configure your virtual host to point to the example/public directory and allow .htaccess overrides:
```apache
<VirtualHost *:80>
    ServerName piri.test
    DocumentRoot /path/to/piri/example/public
    
    <Directory /path/to/piri/example/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Visit your configured domain in the browser

## Available Routes

- `GET /` - Home page with route listing
- `GET /about` - Simple about page
- `GET /users` - List users (requires auth)
- `GET /users/{id}` - Show user details (requires auth)
- `GET /users/{id}/posts` - List user's posts (requires auth)
- `GET /users/{id}/posts/{postId}` - Show specific post (requires auth)
- `POST /users/{id}` - Update user (requires auth)
- `GET /api/status` - API status (requires auth)

## Testing Authentication

For routes that require authentication, you need to include a Bearer token in your request:

```bash
curl -H "Authorization: Bearer any-token" http://localhost:8000/users
```

Any token will work in this example as it's just for demonstration purposes.

## Testing POST Requests

To update a user:

```bash
curl -X POST -H "Authorization: Bearer any-token" \
     -H "Content-Type: application/json" \
     -d '{"name": "Updated Name"}' \
     http://localhost:8000/users/1
```

## Features Demonstrated

1. Attribute-based routing
2. Method-based routing
3. Route groups
4. Route parameters
5. Optional parameters
6. Middleware (auth & logging)
7. Named routes
8. Route patterns
9. Different response types (HTML & JSON)
10. Error handling 