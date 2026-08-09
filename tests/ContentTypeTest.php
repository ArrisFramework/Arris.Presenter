<?php

namespace Tests;

use Arris\Presenter\System\ContentType;
use PHPUnit\Framework\TestCase;

class ContentTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $this->assertSame(
            ['rss', 'xml', 'json', 'json_raw', 'html', 'js', 'js-raw', 'raw', 'raw_html', 'result', 'redirect', '404', '500'],
            array_map(static fn(ContentType $c): string => $c->value, ContentType::cases())
        );
    }

    public function testContentTypeHeader(): void
    {
        $cases = [
            [ContentType::RSS,       'application/xml; charset=utf-8'],
            [ContentType::XML,       'application/xml; charset=utf-8'],
            [ContentType::JSON,      'application/json; charset=utf-8'],
            [ContentType::JSON_RAW,  'application/json; charset=utf-8'],
            [ContentType::RESULT,    'application/json; charset=utf-8'],
            [ContentType::HTML,      'text/html; charset=utf-8'],
            [ContentType::RAW_HTML,  'text/html; charset=utf-8'],
            [ContentType::JS,        'text/javascript; charset=utf-8'],
            [ContentType::JS_RAW,    'text/javascript; charset=utf-8'],
            [ContentType::RAW,       null],
            [ContentType::REDIRECT,  null],
            [ContentType::NOT_FOUND, null],
            [ContentType::INTERNAL_ERROR, null],
        ];

        foreach ($cases as [$type, $expected]) {
            $this->assertSame($expected, $type->contentTypeHeader(), $type->value);
        }
    }

    public function testStatusCode(): void
    {
        $this->assertSame(404, ContentType::NOT_FOUND->statusCode());
        $this->assertSame(500, ContentType::INTERNAL_ERROR->statusCode());

        foreach ([ContentType::HTML, ContentType::JSON, ContentType::REDIRECT, ContentType::RAW] as $type) {
            $this->assertNull($type->statusCode());
        }
    }

    public function testNeedsTemplateRender(): void
    {
        $this->assertTrue(ContentType::HTML->needsTemplateRender());
        $this->assertTrue(ContentType::JS->needsTemplateRender());
        $this->assertTrue(ContentType::XML->needsTemplateRender());
        $this->assertTrue(ContentType::RSS->needsTemplateRender());
        $this->assertTrue(ContentType::NOT_FOUND->needsTemplateRender());
        $this->assertTrue(ContentType::INTERNAL_ERROR->needsTemplateRender());

        $this->assertFalse(ContentType::JSON->needsTemplateRender());
        $this->assertFalse(ContentType::JSON_RAW->needsTemplateRender());
        $this->assertFalse(ContentType::RESULT->needsTemplateRender());
        $this->assertFalse(ContentType::RAW->needsTemplateRender());
        $this->assertFalse(ContentType::RAW_HTML->needsTemplateRender());
        $this->assertFalse(ContentType::JS_RAW->needsTemplateRender());
        $this->assertFalse(ContentType::REDIRECT->needsTemplateRender());
    }

    public function testTryFromKnownValues(): void
    {
        $this->assertSame(ContentType::HTML, ContentType::tryFrom('html'));
        $this->assertSame(ContentType::JSON_RAW, ContentType::tryFrom('json_raw'));
        $this->assertSame(ContentType::NOT_FOUND, ContentType::tryFrom('404'));
    }

    public function testTryFromUnknownValue(): void
    {
        $this->assertNull(ContentType::tryFrom('bogus'));
    }
}
