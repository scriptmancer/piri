<?php

declare(strict_types=1);

namespace Piri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piri\Core\Router;
use Piri\Contracts\MiddlewareInterface;
use Piri\Exceptions\RouteNotFoundException;
use Piri\Exceptions\UnauthorizedException;

class RouterMiddlewareTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testCanAddMiddlewareToRoute(): void
    {
        $this->router->get('/protected', fn() => 'protected');
        $this->router->addMiddleware('GET', '/protected', new class implements MiddlewareInterface {
            public function handle(callable $next, array $parameters = []): mixed
            {
                return $next($parameters);
            }
        });

        $route = $this->router->match('GET', '/protected');
        
        $this->assertCount(1, $route['middleware']);
        $this->assertInstanceOf(MiddlewareInterface::class, $route['middleware'][0]);
    }

    public function testCanAddGlobalMiddleware(): void
    {
        $this->router->addGlobalMiddleware(new class implements MiddlewareInterface {
            public function handle(callable $next, array $parameters = []): mixed
            {
                return $next($parameters);
            }
        });

        $this->router->get('/test', fn() => 'test');
        $route = $this->router->match('GET', '/test');
        
        $this->assertCount(1, $route['middleware']);
        $this->assertInstanceOf(MiddlewareInterface::class, $route['middleware'][0]);
    }

    public function testMiddlewareCanModifyParameters(): void
    {
        $this->router->get('/test/{id}', fn(array $params) => $params['id']);
        
        $this->router->addMiddleware('GET', '/test/{id}', new class implements MiddlewareInterface {
            public function handle(callable $next, array $parameters = []): mixed
            {
                $parameters['id'] = intval($parameters['id']);
                return $next($parameters);
            }
        });

        $route = $this->router->match('GET', '/test/123');
        $result = $this->router->execute($route);
        
        $this->assertSame(123, $result);
    }

    public function testMiddlewareCanBlockExecution(): void
    {
        $this->router->get('/admin', fn() => 'admin area');
        
        $this->router->addMiddleware('GET', '/admin', new class implements MiddlewareInterface {
            public function handle(callable $next, array $parameters = []): mixed
            {
                throw new UnauthorizedException('Access denied');
            }
        });

        $route = $this->router->match('GET', '/admin');
        
        $this->expectException(UnauthorizedException::class);
        $this->router->execute($route);
    }

    public function testMiddlewareExecutesInCorrectOrder(): void
    {
        $order = [];
        
        $this->router->addGlobalMiddleware(new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}
            
            public function handle(callable $next, array $parameters = []): mixed
            {
                $this->order[] = 'global';
                return $next($parameters);
            }
        });

        $this->router->get('/test', fn() => 'test');
        
        $this->router->addMiddleware('GET', '/test', new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}
            
            public function handle(callable $next, array $parameters = []): mixed
            {
                $this->order[] = 'route';
                return $next($parameters);
            }
        });

        $route = $this->router->match('GET', '/test');
        $this->router->execute($route);
        
        $this->assertEquals(['global', 'route'], $order);
    }

    public function testCannotAddMiddlewareToNonExistentRoute(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(callable $next, array $parameters = []): mixed
            {
                return $next($parameters);
            }
        };

        $this->expectException(RouteNotFoundException::class);
        $this->router->addMiddleware('GET', '/non-existent', $middleware);
    }
} 