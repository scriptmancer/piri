<?php

declare(strict_types=1);

namespace Piri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piri\Core\Route;
use ReflectionClass;

class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the static properties before each test
        $this->resetRouteStaticProperties();
    }

    private function resetRouteStaticProperties(): void
    {
        $reflectionClass = new ReflectionClass(Route::class);
        
        $routesProperty = $reflectionClass->getProperty('routes');
        $routesProperty->setValue(null, []);
        
        $namedRoutesProperty = $reflectionClass->getProperty('namedRoutes');
        $namedRoutesProperty->setValue(null, []);
    }

    public function testCanAddNamedRoute(): void
    {
        Route::addNamed('test.route', '/test', ['handler' => fn() => 'test']);
        
        $route = Route::getByName('test.route');
        
        $this->assertNotNull($route);
        $this->assertEquals('/test', $route['path']);
    }

    public function testCanGenerateUrlWithParameters(): void
    {
        Route::addNamed('user.profile', '/users/{id}/profile', ['handler' => fn() => 'test']);
        
        $url = Route::url('user.profile', ['id' => 123]);
        
        $this->assertEquals('/users/123/profile', $url);
    }

    public function testCanGenerateUrlWithMultipleParameters(): void
    {
        Route::addNamed(
            'user.posts',
            '/users/{userId}/posts/{postId}',
            ['handler' => fn() => 'test']
        );
        
        $url = Route::url('user.posts', ['userId' => 123, 'postId' => 456]);
        
        $this->assertEquals('/users/123/posts/456', $url);
    }

    public function testThrowsExceptionForMissingRoute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route [missing.route] not found.');
        
        Route::url('missing.route');
    }

    public function testThrowsExceptionForMissingParameters(): void
    {
        Route::addNamed('user.show', '/users/{id}', ['handler' => fn() => 'test']);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not all parameters were provided for the route.');
        
        Route::url('user.show');
    }

    public function testCanGetNullForNonExistentRoute(): void
    {
        $this->assertNull(Route::getByName('non.existent'));
    }
} 