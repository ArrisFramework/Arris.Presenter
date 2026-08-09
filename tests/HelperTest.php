<?php

namespace Tests;

use Arris\Presenter\Core\Helper;
use PHPUnit\Framework\TestCase;

class HelperInstanceDemo
{
    public function instanceMethod(): string
    {
        return 'instance';
    }

    public function __invoke(): string
    {
        return 'invoke';
    }

    public static function staticMethod(): string
    {
        return 'static';
    }
}

class HelperTest extends TestCase
{
    private array $savedServer = [];

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    public function testIsSslHttpsOn(): void
    {
        $_SERVER = ['HTTPS' => 'on'];
        $this->assertTrue(Helper::is_ssl());
    }

    public function testIsSslHttpsOne(): void
    {
        $_SERVER = ['HTTPS' => '1'];
        $this->assertTrue(Helper::is_ssl());
    }

    public function testIsSslPort443(): void
    {
        $_SERVER = ['SERVER_PORT' => '443'];
        $this->assertTrue(Helper::is_ssl());
    }

    public function testIsSslFalse(): void
    {
        $_SERVER = [];
        $this->assertFalse(Helper::is_ssl());
    }

    public function testIsSslHttpsOff(): void
    {
        $_SERVER = ['HTTPS' => 'off'];
        $this->assertFalse(Helper::is_ssl());
    }

    public function testGetAllowedValueFound(): void
    {
        $this->assertSame('html', Helper::getAllowedValue('html', ['html', 'json']));
    }

    public function testGetAllowedValueNotFound(): void
    {
        $this->assertSame('default', Helper::getAllowedValue('xml', ['html', 'json'], 'default'));
    }

    public function testGetAllowedValueEmptyData(): void
    {
        $this->assertSame('default', Helper::getAllowedValue('', ['html'], 'default'));
        $this->assertSame('default', Helper::getAllowedValue(null, ['html'], 'default'));
    }

    public function testCompileHandlerClosure(): void
    {
        $closure = static fn() => 'x';
        $this->assertSame($closure, Helper::compileHandler($closure));
    }

    public function testCompileHandlerArrayForm(): void
    {
        $actor = Helper::compileHandler([HelperInstanceDemo::class, 'instanceMethod']);
        $this->assertIsArray($actor);
        $this->assertInstanceOf(HelperInstanceDemo::class, $actor[0]);
        $this->assertSame('instanceMethod', $actor[1]);
    }

    public function testCompileHandlerAtForm(): void
    {
        $actor = Helper::compileHandler(HelperInstanceDemo::class . '@instanceMethod');
        $this->assertInstanceOf(HelperInstanceDemo::class, $actor[0]);
        $this->assertSame('instanceMethod', $actor[1]);
    }

    public function testCompileHandlerStaticForm(): void
    {
        $actor = Helper::compileHandler(HelperInstanceDemo::class . '::staticMethod');
        $this->assertSame([HelperInstanceDemo::class, 'staticMethod'], $actor);
    }

    public function testCompileHandlerFunctionName(): void
    {
        $this->assertSame('strtoupper', Helper::compileHandler('strtoupper'));
    }

    public function testCompileHandlerEmpty(): void
    {
        $this->assertSame([], Helper::compileHandler(''));
        $this->assertSame([], Helper::compileHandler(null));
    }
}
