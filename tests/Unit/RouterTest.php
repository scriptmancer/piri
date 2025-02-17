<?php

declare(strict_types=1);

namespace Piri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piri\Core\Router;
use Piri\Core\Route;
use Piri\Contracts\MiddlewareInterface;
use Piri\Exceptions\RouteNotFoundException;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testCanAddRoute(): void
    {
        $this->router->add('GET', '/test', fn() => 'test');

        $route = $this->router->match('GET', '/test');
        
        $this->assertIsArray($route);
        $this->assertArrayHasKey('handler', $route);
        $this->assertArrayHasKey('middleware', $route);
    }

    public function testCanAddNamedRoute(): void
    {
        $this->router->add('GET', '/users/{id}', fn() => 'test', 'user.show');

        $route = Route::getByName('user.show');
        
        $this->assertNotNull($route);
        $this->assertArrayHasKey('path', $route);
        $this->assertEquals('/users/{id}', $route['path']);
    }

    public function testCanGenerateUrlForNamedRoute(): void
    {
        $this->router->add('GET', '/users/{id}', fn() => 'test', 'user.show');

        $url = Route::url('user.show', ['id' => 1]);
        
        $this->assertEquals('/users/1', $url);
    }

    public function testThrowsExceptionForNonExistentRoute(): void
    {
        $this->expectException(RouteNotFoundException::class);
        
        $this->router->match('GET', '/non-existent');
    }

    public function testThrowsExceptionForInvalidNamedRoute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        Route::url('non.existent');
    }

    public function testThrowsExceptionForMissingParameters(): void
    {
        $this->router->add('GET', '/users/{id}', fn() => 'test', 'user.show');

        $this->expectException(\InvalidArgumentException::class);
        
        Route::url('user.show');
    }

    public function testCanAddRouteWithHttpMethodHelpers(): void
    {
        $this->router->get('/get', fn() => 'get');
        $this->router->post('/post', fn() => 'post');
        $this->router->put('/put', fn() => 'put');
        $this->router->delete('/delete', fn() => 'delete');

        $this->assertIsArray($this->router->match('GET', '/get'));
        $this->assertIsArray($this->router->match('POST', '/post'));
        $this->assertIsArray($this->router->match('PUT', '/put'));
        $this->assertIsArray($this->router->match('DELETE', '/delete'));
    }

    public function testCanMatchRouteWithSingleParameter(): void
    {
        $this->router->get('/users/{id}', fn() => 'test');

        $route = $this->router->match('GET', '/users/123');
        
        $this->assertIsArray($route);
        $this->assertArrayHasKey('parameters', $route);
        $this->assertEquals(['id' => '123'], $route['parameters']);
    }

    public function testCanMatchRouteWithMultipleParameters(): void
    {
        $this->router->get('/users/{userId}/posts/{postId}', fn() => 'test');

        $route = $this->router->match('GET', '/users/123/posts/456');
        
        $this->assertIsArray($route);
        $this->assertArrayHasKey('parameters', $route);
        $this->assertEquals(
            ['userId' => '123', 'postId' => '456'],
            $route['parameters']
        );
    }

    public function testCanMatchRouteWithParametersInMiddle(): void
    {
        $this->router->get('/users/{id}/profile/edit', fn() => 'test');

        $route = $this->router->match('GET', '/users/123/profile/edit');
        
        $this->assertIsArray($route);
        $this->assertArrayHasKey('parameters', $route);
        $this->assertEquals(['id' => '123'], $route['parameters']);
    }

    public function testDoesNotMatchInvalidParameterPattern(): void
    {
        $this->router->get('/users/{id}', fn() => 'test');

        $this->expectException(RouteNotFoundException::class);
        
        $this->router->match('GET', '/users/123/invalid');
    }

    public function testCanCreateRouteGroup(): void
    {
        $this->router->group(['prefix' => '/admin'], function (Router $router) {
            $router->get('/users', fn() => 'users');
            $router->get('/posts', fn() => 'posts');
        });

        $usersRoute = $this->router->match('GET', '/admin/users');
        $postsRoute = $this->router->match('GET', '/admin/posts');

        $this->assertIsArray($usersRoute);
        $this->assertIsArray($postsRoute);
    }

    public function testCanNestRouteGroups(): void
    {
        $this->router->group(['prefix' => '/api'], function (Router $router) {
            $router->group(['prefix' => '/v1'], function (Router $router) {
                $router->get('/users', fn() => 'users');
            });
        });

        $route = $this->router->match('GET', '/api/v1/users');
        
        $this->assertIsArray($route);
    }

    public function testRouteGroupInheritsMiddleware(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(callable $next, array $parameters = []): mixed
            {
                return $next($parameters);
            }
        };

        $this->router->group(['middleware' => [$middleware]], function (Router $router) {
            $router->get('/protected', fn() => 'protected');
        });

        $route = $this->router->match('GET', '/protected');
        
        $this->assertCount(1, $route['middleware']);
        $this->assertInstanceOf(MiddlewareInterface::class, $route['middleware'][0]);
    }

    public function testCanMatchRouteWithOptionalParameter(): void
    {
        $this->router->get('/users/{id?}', fn(array $params) => $params['id'] ?? 'all');

        // Test with parameter
        $routeWithParam = $this->router->match('GET', '/users/123');
        $this->assertEquals(['id' => '123'], $routeWithParam['parameters']);

        // Test without parameter
        $routeWithoutParam = $this->router->match('GET', '/users');
        $this->assertEquals(['id' => null], $routeWithoutParam['parameters']);
    }

    public function testCanMatchRouteWithMultipleOptionalParameters(): void
    {
        $this->router->get('/users/{id?}/posts/{postId?}', fn() => 'test');

        // Test with all parameters
        $route1 = $this->router->match('GET', '/users/123/posts/456');
        $this->assertEquals(
            ['id' => '123', 'postId' => '456'],
            $route1['parameters']
        );

        // Test with first parameter only
        $route2 = $this->router->match('GET', '/users/123/posts');
        $this->assertEquals(
            ['id' => '123', 'postId' => null],
            $route2['parameters']
        );

        // Test with no parameters
        $route3 = $this->router->match('GET', '/users/posts');
        $this->assertEquals(
            ['id' => null, 'postId' => null],
            $route3['parameters']
        );
    }

    public function testOptionalParameterInMiddle(): void
    {
        $this->router->get('/users/{id?}/profile', fn() => 'test');

        // Test with parameter
        $routeWithParam = $this->router->match('GET', '/users/123/profile');
        $this->assertEquals(['id' => '123'], $routeWithParam['parameters']);

        // Test without parameter
        $routeWithoutParam = $this->router->match('GET', '/users/profile');
        $this->assertEquals(['id' => null], $routeWithoutParam['parameters']);
    }
} 