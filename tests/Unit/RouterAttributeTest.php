<?php

declare(strict_types=1);

namespace Piri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piri\Core\Router;
use Piri\Attributes\Route;
use Piri\Contracts\MiddlewareInterface;
use Piri\Core\Route as CoreRoute;

#[Route(prefix: '/api', middleware: [TestMiddleware::class])]
class TestController
{
    #[Route('/users', name: 'users.list')]
    public function listUsers(): string
    {
        return 'users list';
    }

    #[Route('/users/{id}', methods: ['GET', 'POST'], name: 'users.show')]
    public function showUser(array $params): string
    {
        return 'user ' . $params['id'];
    }

    #[Route('/admin', middleware: [AdminMiddleware::class])]
    public function adminArea(): string
    {
        return 'admin area';
    }
}

class TestMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        return $next($parameters);
    }
}

class AdminMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        return $next($parameters);
    }
}

class FirstMiddleware implements MiddlewareInterface
{
    public function __construct(private array &$order) {}
    
    public function handle(callable $next, array $parameters = []): mixed
    {
        $this->order[] = 'first';
        return $next($parameters);
    }
}

class SecondMiddleware implements MiddlewareInterface
{
    public function __construct(private array &$order) {}
    
    public function handle(callable $next, array $parameters = []): mixed
    {
        $this->order[] = 'second';
        return $next($parameters);
    }
}

class RouterAttributeTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        CoreRoute::clearNamed();
    }

    public function testCanRegisterClassWithAttributes(): void
    {
        $this->router->registerClass(TestController::class);

        $route = $this->router->match('GET', '/api/users');
        $this->assertIsArray($route);
        $this->assertArrayHasKey('handler', $route);
        $this->assertCount(1, $route['middleware']);
    }

    public function testCanMatchRouteWithParameters(): void
    {
        $this->router->registerClass(TestController::class);

        $route = $this->router->match('GET', '/api/users/123');
        $this->assertEquals(['id' => '123'], $route['parameters']);
    }

    public function testCanHandleMultipleMethods(): void
    {
        $this->router->registerClass(TestController::class);

        $getRoute = $this->router->match('GET', '/api/users/123');
        $postRoute = $this->router->match('POST', '/api/users/123');

        $this->assertIsArray($getRoute);
        $this->assertIsArray($postRoute);
    }

    public function testCanHandleMethodSpecificMiddleware(): void
    {
        $this->router->registerClass(TestController::class);

        $route = $this->router->match('GET', '/api/admin');
        $this->assertCount(2, $route['middleware']);
        $this->assertInstanceOf(TestMiddleware::class, $route['middleware'][0]);
        $this->assertInstanceOf(AdminMiddleware::class, $route['middleware'][1]);
    }

    public function testCanHandleCallableMiddleware(): void
    {
        $controller = new class {
            #[Route('/test', middleware: 'Piri\Tests\Unit\CallableTestMiddleware')]
            public function test(): string
            {
                return 'test';
            }
        };

        $this->router->registerClass($controller);

        $route = $this->router->match('GET', '/test');
        $this->assertCount(1, $route['middleware']);
        $this->assertInstanceOf(MiddlewareInterface::class, $route['middleware'][0]);
    }

    public function testThrowsExceptionForNonExistentRoute(): void
    {
        $this->router->registerClass(TestController::class);
        
        $this->expectException(\Piri\Exceptions\RouteNotFoundException::class);
        $this->router->match('GET', '/api/non-existent');
    }

    public function testCanHandleRouteGroups(): void
    {
        $controller = new class {
            #[Route('/test', group: 'admin')]
            public function test(): string
            {
                return 'test';
            }
        };

        $this->router->registerClass($controller);
        
        $route = $this->router->match('GET', '/test');
        $this->assertIsArray($route);
        $this->assertArrayHasKey('group', $route);
        $this->assertEquals('admin', $route['group']);
    }

    public function testThrowsExceptionForDuplicateRouteNames(): void
    {
        $controller = new class {
            #[Route('/test1', name: 'test')]
            public function test1(): string
            {
                return 'test1';
            }

            #[Route('/test2', name: 'test')]
            public function test2(): string
            {
                return 'test2';
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate route name: test');
        $this->router->registerClass($controller);
    }

    public function testCanGenerateUrlForNamedRoute(): void
    {
        $controller = new class {
            #[Route('/users/{id}/posts/{postId}', name: 'user.post')]
            public function showPost(): string
            {
                return 'post';
            }
        };

        $this->router->registerClass($controller);
        
        $url = CoreRoute::url('user.post', ['id' => '123', 'postId' => '456']);
        $this->assertEquals('/users/123/posts/456', $url);
    }

    public function testThrowsExceptionForMissingParameters(): void
    {
        $controller = new class {
            #[Route('/users/{id}', name: 'user.show')]
            public function show(): string
            {
                return 'user';
            }
        };

        $this->router->registerClass($controller);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not all parameters were provided for the route.');
        CoreRoute::url('user.show', []);
    }

    public function testCanHandleOptionalParameters(): void
    {
        $controller = new class {
            #[Route('/users/{id}/{tab?}', name: 'user.profile')]
            public function profile(): string
            {
                return 'profile';
            }
        };

        $this->router->registerClass($controller);
        
        $route = $this->router->match('GET', '/users/123');
        $this->assertIsArray($route);
        $this->assertEquals(['id' => '123', 'tab' => null], $route['parameters']);

        $route = $this->router->match('GET', '/users/123/settings');
        $this->assertEquals(['id' => '123', 'tab' => 'settings'], $route['parameters']);
    }

    public function testCanHandleHttpMethods(): void
    {
        $controller = new class {
            #[Route('/resource', methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])]
            public function handle(): string
            {
                return 'handled';
            }
        };

        $this->router->registerClass($controller);
        
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        foreach ($methods as $method) {
            $route = $this->router->match($method, '/resource');
            $this->assertIsArray($route);
            $this->assertArrayHasKey('handler', $route);
        }
    }

    public function testRoutePatternValidation(): void
    {
        $controller = new class {
            #[Route('/users/{id:\d+}', name: 'users.numeric')]
            public function numericId(): string
            {
                return 'numeric';
            }

            #[Route('/users/{username:[a-zA-Z]+}', name: 'users.alpha')]
            public function alphaUsername(): string
            {
                return 'alpha';
            }
        };

        $this->router->registerClass($controller);
        
        $route = $this->router->match('GET', '/users/123');
        $this->assertEquals(['id' => '123'], $route['parameters']);

        $route = $this->router->match('GET', '/users/john');
        $this->assertEquals(['username' => 'john'], $route['parameters']);
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $order = [];
        TestOrderMiddleware::$order = &$order;

        $controller = new class {
            #[Route('/test', middleware: [TestOrderMiddleware::class])]
            public function test(): string
            {
                return 'test';
            }
        };

        $this->router->registerClass($controller);
        
        $route = $this->router->match('GET', '/test');
        $this->router->execute($route);
        
        $this->assertEquals(['middleware'], $order);
    }

    public function testHeadMethodAutoHandling(): void
    {
        $controller = new class {
            #[Route('/test', methods: ['GET'])]
            public function test(): string
            {
                return 'test';
            }
        };

        $this->router->registerClass($controller);
        
        $route = $this->router->match('HEAD', '/test');
        $this->assertIsArray($route);
        $this->assertArrayHasKey('handler', $route);
    }

    public function testRoutePriorityOrder(): void
    {
        $controller = new class {
            #[Route('/users/special')]
            public function special(): string
            {
                return 'special';
            }

            #[Route('/users/{id}')]
            public function show(): string
            {
                return 'show';
            }
        };

        $this->router->registerClass($controller);
        
        $route = $this->router->match('GET', '/users/special');
        $this->assertIsArray($route);
        $handler = $route['handler'];
        $this->assertEquals('special', $handler[1]);
    }
}

class CallableTestMiddleware implements MiddlewareInterface
{
    public function handle(callable $next, array $parameters = []): mixed
    {
        return $next($parameters);
    }
}

class TestOrderMiddleware implements MiddlewareInterface
{
    public static array $order = [];

    public function handle(callable $next, array $parameters = []): mixed
    {
        self::$order[] = 'middleware';
        return $next($parameters);
    }
} 