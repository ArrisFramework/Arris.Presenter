<?php

namespace Tests;

use Arris\Presenter\Breadcrumbs;
use PHPUnit\Framework\TestCase;

class BreadcrumbsTest extends TestCase
{
    public function testEmptyImplodeCrumbs(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $this->assertSame('', $breadcrumbs->implodeCrumbs());
    }

    public function testEmptyCrumbsToTitles(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $this->assertSame([], $breadcrumbs->crumbsToTitles());
    }

    public function testAddCrumbAndImplodeDefaultMask(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $this->assertSame($breadcrumbs, $breadcrumbs->addCrumb('/', 'Home'));
        $breadcrumbs->addCrumb('/about', 'About');

        $expected = '<a href="/">Home</a><a href="/about">About</a>';
        $this->assertSame($expected, $breadcrumbs->implodeCrumbs());
    }

    public function testImplodeCrumbsWithCrlf(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addCrumb('/', 'Home');
        $breadcrumbs->addCrumb('/about', 'About');

        $expected = "<a href=\"/\">Home</a>\n<a href=\"/about\">About</a>";
        $this->assertSame($expected, $breadcrumbs->implodeCrumbs('<a href="%s">%s</a>', true));
    }

    public function testImplodeCrumbsCustomMask(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addCrumb('/', 'Home');

        $this->assertSame('<li>/ — Home</li>', $breadcrumbs->implodeCrumbs('<li>%s — %s</li>'));
    }

    public function testAddPath(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addPath(['url' => '/x', 'title' => 'X']);

        $this->assertSame('<a href="/x">X</a>', $breadcrumbs->implodeCrumbs());
    }

    public function testAddTitle(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $this->assertSame($breadcrumbs, $breadcrumbs->addTitle('First'));
        $breadcrumbs->addTitle('Second');

        $this->assertSame(['First', 'Second'], $breadcrumbs->getTitles());
    }

    public function testGetCrumbs(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addCrumb('/', 'Home');
        $breadcrumbs->addCrumb('/about', 'About');

        $this->assertSame(
            [['url' => '/', 'title' => 'Home'], ['url' => '/about', 'title' => 'About']],
            $breadcrumbs->getCrumbs()
        );
        $this->assertSame(
            [['url' => '/about', 'title' => 'About'], ['url' => '/', 'title' => 'Home']],
            $breadcrumbs->getCrumbs(false)
        );
    }

    public function testGetTitlesReverse(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addTitle('One');
        $breadcrumbs->addTitle('Two');

        $this->assertSame(['One', 'Two'], $breadcrumbs->getTitles());
        $this->assertSame(['Two', 'One'], $breadcrumbs->getTitles(false));
    }

    public function testCrumbsToTitles(): void
    {
        $breadcrumbs = new Breadcrumbs();
        $breadcrumbs->addCrumb('/', 'Home');
        $breadcrumbs->addCrumb('/about', 'About');

        $this->assertSame(['Home', 'About'], $breadcrumbs->crumbsToTitles());
    }
}
