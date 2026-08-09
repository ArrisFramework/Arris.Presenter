<?php

namespace Tests;

use Arris\Presenter\Headers;
use PHPUnit\Framework\TestCase;

class HeadersTest extends TestCase
{
    private array $savedServer = [];

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    public function testInitialState(): void
    {
        $headers = new Headers();
        $this->assertSame([], $headers->current_headers);
        $this->assertFalse($headers->headers_present);
        $this->assertTrue($headers->isEmpty());
    }

    public function testAddNameContent(): void
    {
        $headers = new Headers();
        $this->assertSame($headers, $headers->add(Headers::X_FRAME_OPTIONS, 'DENY'));

        $this->assertTrue($headers->headers_present);
        $this->assertSame('X-Frame-Options: DENY', $headers->current_headers[0]['value']);
        $this->assertSame('X-Frame-Options', $headers->current_headers[0]['name']);
        $this->assertSame('DENY', $headers->current_headers[0]['content']);
        $this->assertTrue($headers->current_headers[0]['replace']);
        $this->assertSame(0, $headers->current_headers[0]['code']);
    }

    public function testAddNoopWhenEverythingEmpty(): void
    {
        $headers = new Headers();
        $headers->add();
        $this->assertTrue($headers->isEmpty());
        $this->assertFalse($headers->headers_present);
    }

    public function testAddStatusCode(): void
    {
        $headers = new Headers();
        $headers->add('', '', true, 404);

        $this->assertSame('HTTP/1.1 404 Not Found', $headers->current_headers[0]['value']);
        $this->assertSame(404, $headers->current_headers[0]['code']);
    }

    public function testAddStatusCodeWithCustomReason(): void
    {
        $headers = new Headers();
        $headers->add('', 'Custom Reason', true, 404);

        $this->assertSame('HTTP/1.1 404 Custom Reason', $headers->current_headers[0]['value']);
    }

    public function testAddContentOnly(): void
    {
        $headers = new Headers();
        $headers->add('', 'Content-Type: text/plain');
        $this->assertSame('Content-Type: text/plain', $headers->current_headers[0]['value']);
    }

    public function testHasIsCaseInsensitive(): void
    {
        $headers = new Headers();
        $headers->add('X-Frame-Options', 'DENY');

        $this->assertTrue($headers->has('X-Frame-Options'));
        $this->assertTrue($headers->has('x-frame-options'));
        $this->assertFalse($headers->has('Location'));
    }

    public function testClean(): void
    {
        $headers = new Headers();
        $headers->add(Headers::CONTENT_TYPE, 'text/html');
        $headers->clean();

        $this->assertTrue($headers->isEmpty());
        $this->assertFalse($headers->headers_present);
    }

    public function testSendReturnsFalseWhenNoHeaders(): void
    {
        $headers = new Headers();
        $this->assertFalse($headers->send());
    }

    public function testSendReturnsTrueWhenHeadersPresent(): void
    {
        $headers = new Headers();
        $headers->add(Headers::CONTENT_TYPE, 'text/html');
        $this->assertTrue($headers->send());
    }

    public function testHttpCodesMap(): void
    {
        $this->assertSame('OK', Headers::HTTP_CODES[200]);
        $this->assertSame('Not Found', Headers::HTTP_CODES[404]);
        $this->assertSame('Internal Server Error', Headers::HTTP_CODES[500]);
        $this->assertSame("I'm a teapot", Headers::HTTP_CODES[418]);
    }

    public function testUnderscoreConstantIsEmpty(): void
    {
        $this->assertSame('', Headers::_);
    }
}
