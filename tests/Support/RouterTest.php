<?php

use App\Support\Request;
use App\Support\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesToMatchingRoute(): void
    {
        $router = new Router();
        $router->add('GET', 'ping', fn (Request $r) => ['status' => 200, 'body' => ['ok' => true]]);

        $result = $router->dispatch(new Request('GET', 'ping'));

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
    }

    public function testReturns404ForUnknownAction(): void
    {
        $router = new Router();

        $result = $router->dispatch(new Request('GET', 'nonexistent'));

        $this->assertSame(404, $result['status']);
    }

    public function testMethodMismatchIsNotFound(): void
    {
        $router = new Router();
        $router->add('GET', 'ping', fn (Request $r) => ['status' => 200, 'body' => []]);

        $result = $router->dispatch(new Request('POST', 'ping'));

        $this->assertSame(404, $result['status']);
    }
}
