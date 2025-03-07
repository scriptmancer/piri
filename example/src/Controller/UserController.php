<?php

namespace Example\Controller;

use Piri\Attributes\Route;
use Example\Middleware\AuthMiddleware;

#[Route(prefix: '/users')]
class UserController
{
    private array $users = [
        1 => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        2 => ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ];

    #[Route('', name: 'users.list')]
    public function index(): array
    {
        return ['users' => array_values($this->users)];
    }

    #[Route('/{id:\d+}', name: 'users.show', middleware: [AuthMiddleware::class])]
    public function show(array $params): array
    {
        $id = (int) $params['id'];
        
        if (!isset($this->users[$id])) {
            http_response_code(404);
            return ['error' => 'User not found'];
        }

        return ['user' => $this->users[$id]];
    }

    #[Route('/{username:[a-zA-Z]+}')]
    public function alphaUsername(array $params): string
    {
        $username = $params['username'] ?? 'unknown';
        return "Username: {$username}";
    }
    
    #[Route('/{id:\d+}/posts/{postId?}', name: 'users.posts')]
    public function posts(array $params): array
    {
        $id = (int) $params['id'];
        $posts = [
            ['id' => 1, 'title' => 'First Post'],
            ['id' => 2, 'title' => 'Second Post'],
        ];

        if (isset($params['postId'])) {
            $postId = (int) $params['postId'];
            $post = array_filter($posts, fn($p) => $p['id'] === $postId);
            return ['user_id' => $id, 'post' => reset($post) ?: null];
        }

        return ['user_id' => $id, 'posts' => $posts];
    }

    #[Route('/{id:\d+}', methods: ['POST'], name: 'users.update')]
    public function update(array $params): array
    {
        $id = (int) $params['id'];
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($this->users[$id])) {
            http_response_code(404);
            return ['error' => 'User not found'];
        }

        $this->users[$id] = array_merge($this->users[$id], $input);
        return ['user' => $this->users[$id]];
    }
} 