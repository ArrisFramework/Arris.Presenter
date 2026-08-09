<?php

namespace Tests;

use Arris\Presenter\Meta;
use PHPUnit\Framework\TestCase;

class MetaTest extends TestCase
{
    public function testDefaults(): void
    {
        $data = (new Meta())->toArray();

        $this->assertSame('website', $data['og']['type']);
        $this->assertSame('ru_RU', $data['og']['locale']);
        $this->assertSame('summary', $data['twitter']['card']);

        $this->assertSame('', $data['title']);
        $this->assertSame([], $data['custom']);
    }

    public function testSettersAreChainable(): void
    {
        $meta = new Meta();
        $this->assertSame($meta, $meta->setTitle('T'));
        $this->assertSame($meta, $meta->setOgType('article'));
        $this->assertSame($meta, $meta->setTwitterCard('summary_large_image'));
        $this->assertSame($meta, $meta->addOgLocaleAlternate('en_US'));
        $this->assertSame($meta, $meta->addCustom('x', 'y'));
    }

    public function testOgFallbacks(): void
    {
        $meta = (new Meta())
            ->setTitle('The Title')
            ->setDescription('The Description')
            ->setCanonical('https://example.com/');

        $data = $meta->toArray();

        $this->assertSame('The Title', $data['og']['title']);
        $this->assertSame('The Description', $data['og']['description']);
        $this->assertSame('https://example.com/', $data['og']['url']);
    }

    public function testExplicitOgOverridesFallback(): void
    {
        $meta = (new Meta())
            ->setTitle('The Title')
            ->setOgTitle('OG Title')
            ->setOgUrl('https://example.com/og');

        $data = $meta->toArray();

        $this->assertSame('OG Title', $data['og']['title']);
        $this->assertSame('https://example.com/og', $data['og']['url']);
    }

    public function testTwitterFallbacks(): void
    {
        $meta = (new Meta())
            ->setTitle('The Title')
            ->setOgImage('https://example.com/og.png');

        $data = $meta->toArray();

        $this->assertSame('The Title', $data['twitter']['title']);
        $this->assertSame('https://example.com/og.png', $data['twitter']['image']);
    }

    public function testTwitterImageExplicitWins(): void
    {
        $meta = (new Meta())
            ->setOgImage('https://example.com/og.png')
            ->setTwitterImage('https://example.com/tw.png');

        $this->assertSame('https://example.com/tw.png', $meta->toArray()['twitter']['image']);
    }

    public function testClearResetsToDefaults(): void
    {
        $meta = (new Meta())
            ->setTitle('T')
            ->setOgType('article')
            ->setTwitterCard('summary_large_image')
            ->addOgLocaleAlternate('en_US')
            ->addCustom('x', 'y');

        $meta->clear();

        $this->assertSame((new Meta())->toArray(), $meta->toArray());
        $this->assertSame((new Meta())->render(), $meta->render());
    }

    public function testRenderEmptyMeta(): void
    {
        $expected = implode("\n", [
            '<meta property="og:type" content="website">',
            '    <meta property="og:locale" content="ru_RU">',
            '    <meta name="twitter:card" content="summary">',
        ]);

        $this->assertSame($expected, (new Meta())->render());
    }

    public function testRenderTitleAndMetaTags(): void
    {
        $meta = (new Meta())
            ->setTitle('My <Title>')
            ->setDescription('Desc & more')
            ->setKeywords('k1, k2')
            ->setRobots('index,follow')
            ->setAuthor('Arris');

        $expected = implode("\n", [
            '<title>My &lt;Title&gt;</title>',
            '    <meta name="description" content="Desc &amp; more">',
            '    <meta name="keywords" content="k1, k2">',
            '    <meta name="robots" content="index,follow">',
            '    <meta name="author" content="Arris">',
            '    <meta property="og:type" content="website">',
            '    <meta property="og:title" content="My &lt;Title&gt;">',
            '    <meta property="og:description" content="Desc &amp; more">',
            '    <meta property="og:locale" content="ru_RU">',
            '    <meta name="twitter:card" content="summary">',
            '    <meta name="twitter:title" content="My &lt;Title&gt;">',
            '    <meta name="twitter:description" content="Desc &amp; more">',
        ]);

        $this->assertSame($expected, $meta->render());
    }

    public function testRenderCanonicalLink(): void
    {
        $meta = (new Meta())->setCanonical('https://example.com/page');

        $expected = implode("\n", [
            '<link rel="canonical" href="https://example.com/page">',
            '    <meta property="og:type" content="website">',
            '    <meta property="og:url" content="https://example.com/page">',
            '    <meta property="og:locale" content="ru_RU">',
            '    <meta name="twitter:card" content="summary">',
        ]);
        $this->assertSame($expected, $meta->render());
    }

    public function testRenderFullStructure(): void
    {
        $meta = (new Meta())
            ->setTitle('My <Title>')
            ->setDescription('Desc & more')
            ->setKeywords('k1, k2')
            ->setRobots('index,follow')
            ->setCanonical('https://example.com/page')
            ->setAuthor('Arris')
            ->setOgImage('https://example.com/img.png')
            ->setOgSiteName('Site')
            ->addOgLocaleAlternate('en_US')
            ->setTwitterImage('https://example.com/tw.png')
            ->addCustom('theme-color', '#fff');

        $expected = implode("\n", [
            '<title>My &lt;Title&gt;</title>',
            '    <meta name="description" content="Desc &amp; more">',
            '    <meta name="keywords" content="k1, k2">',
            '    <meta name="robots" content="index,follow">',
            '    <meta name="author" content="Arris">',
            '    <link rel="canonical" href="https://example.com/page">',
            '    <meta property="og:type" content="website">',
            '    <meta property="og:title" content="My &lt;Title&gt;">',
            '    <meta property="og:description" content="Desc &amp; more">',
            '    <meta property="og:image" content="https://example.com/img.png">',
            '    <meta property="og:url" content="https://example.com/page">',
            '    <meta property="og:site_name" content="Site">',
            '    <meta property="og:locale" content="ru_RU">',
            '    <meta property="og:locale:alternate" content="en_US">',
            '    <meta name="twitter:card" content="summary">',
            '    <meta name="twitter:title" content="My &lt;Title&gt;">',
            '    <meta name="twitter:description" content="Desc &amp; more">',
            '    <meta name="twitter:image" content="https://example.com/tw.png">',
            '    <meta name="theme-color" content="#fff">',
        ]);

        $this->assertSame($expected, $meta->render());
    }

    public function testRenderCustomIndent(): void
    {
        $meta = (new Meta())
            ->setTitle('T')
            ->setDescription('D');

        $expected = implode("\n", [
            '<title>T</title>',
            "\t<meta name=\"description\" content=\"D\">",
            "\t<meta property=\"og:type\" content=\"website\">",
            "\t<meta property=\"og:title\" content=\"T\">",
            "\t<meta property=\"og:description\" content=\"D\">",
            "\t<meta property=\"og:locale\" content=\"ru_RU\">",
            "\t<meta name=\"twitter:card\" content=\"summary\">",
            "\t<meta name=\"twitter:title\" content=\"T\">",
            "\t<meta name=\"twitter:description\" content=\"D\">",
        ]);

        $this->assertSame($expected, $meta->render(1, "\t"));
    }

    public function testRenderEscapesQuotes(): void
    {
        $meta = (new Meta())->setDescription('He said "hello"');

        $expected = implode("\n", [
            '<meta name="description" content="He said &quot;hello&quot;">',
            '    <meta property="og:type" content="website">',
            '    <meta property="og:description" content="He said &quot;hello&quot;">',
            '    <meta property="og:locale" content="ru_RU">',
            '    <meta name="twitter:card" content="summary">',
            '    <meta name="twitter:description" content="He said &quot;hello&quot;">',
        ]);

        $this->assertSame($expected, $meta->render());
    }

    public function testToArrayStructure(): void
    {
        $meta = (new Meta())->setTitle('T')->setOgImage('img.png');

        $data = $meta->toArray();

        $this->assertSame(['title', 'description', 'keywords', 'robots', 'canonical', 'author'], array_keys(array_slice($data, 0, 6)));
        $this->assertSame(['type', 'title', 'description', 'image', 'url', 'site_name', 'locale', 'locale_alternates'], array_keys($data['og']));
        $this->assertSame(['card', 'title', 'description', 'image'], array_keys($data['twitter']));
    }
}
