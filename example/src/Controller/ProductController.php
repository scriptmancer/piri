<?php

namespace Example\Controller;

use Example\Middleware\AuthMiddleware;
use Piri\Attributes\Route;
use Piri\Attributes\RouteGroup;

#[RouteGroup(prefix: '/products', name: 'products' )]
class ProductController
{
    #[Route('/', name: 'index')]
    public function index(): string
    {
        return 'Products';
    }

    #[Route('/{id}', name: 'show')]
    public function show(int $id): string
    {
        return "Product $id";
    }
}
