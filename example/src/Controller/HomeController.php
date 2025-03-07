<?php

namespace Example\Controller;

use Piri\Attributes\Route;

class HomeController
{
    #[Route('/', name: 'home')]
    public function index(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>Piri Router Example</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                h1 { color: #333; }
                .routes { margin: 20px 0; }
                .route { background: #f5f5f5; padding: 10px; margin: 5px 0; border-radius: 4px; }
            </style>
        </head>
        <body>
            <h1>Welcome to Piri Router Example</h1>
            <div class="routes">
                <h2>Available Routes:</h2>
                <div class="route">GET / - This page</div>
                <div class="route">GET /users - List users</div>
                <div class="route">GET /users/123 - Show user</div>
                <div class="route">GET /api/status - API status (requires auth)</div>
            </div>
        </body>
        </html>
        HTML;
    }

    #[Route('/about', name: 'about')]
    public function about(): string
    {
        return 'About Page';
    }

    #[Route('/config_to_attribute', group:'api_root')]
    public function apiRoot(): string
    {
        return 'This route is registered with the api_root group which is defined in the routes.php file';
    }

    #[Route('/status', group:'api_root')]
    public function status(): string
    {
        return 'This route is registered with the api_root group which is defined in the routes.php file';
    }
} 