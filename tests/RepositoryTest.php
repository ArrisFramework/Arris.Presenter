<?php

namespace Tests;

use Arris\Presenter\Core\Repository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    public function testConstructFromArray(): void
    {
        $repo = new Repository(['a' => 1]);
        $this->assertSame(['a' => 1], $repo->all());
    }

    public function testConstructFromRepository(): void
    {
        $inner = new Repository(['x' => 'y']);
        $outer = new Repository($inner);
        $this->assertSame(['x' => 'y'], $outer->all());
    }

    public function testConstructFromScalar(): void
    {
        $repo = new Repository('scalar');
        $this->assertSame(['scalar'], $repo->all());
    }

    public function testSetGet(): void
    {
        $repo = new Repository();
        $this->assertSame($repo, $repo->set('key', 'value'));
        $this->assertSame('value', $repo->get('key'));
    }

    public function testSetArray(): void
    {
        $repo = new Repository();
        $repo->set(['a' => 1, 'b' => 2]);
        $this->assertSame(1, $repo->get('a'));
        $this->assertSame(2, $repo->get('b'));
    }

    public function testGetDefault(): void
    {
        $repo = new Repository(['a' => 1]);
        $this->assertSame('fallback', $repo->get('missing', 'fallback'));
        $this->assertNull($repo->get('missing'));
    }

    public function testGetAllWithoutKey(): void
    {
        $repo = new Repository(['a' => 1]);
        $this->assertSame(['a' => 1], $repo->get());
    }

    public function testHas(): void
    {
        $repo = new Repository(['a' => null]);
        $this->assertTrue($repo->has('a'));
        $this->assertFalse($repo->has('b'));
    }

    public function testDelete(): void
    {
        $repo = new Repository(['a' => 1, 'b' => 2]);
        $this->assertSame($repo, $repo->delete('a'));
        $this->assertFalse($repo->has('a'));
        $this->assertTrue($repo->has('b'));

        $repo->delete(['b', 'missing']);
        $this->assertSame([], $repo->all());
    }

    public function testOffsetAccess(): void
    {
        $repo = new Repository(['a' => 1]);

        $this->assertTrue(isset($repo['a']));
        $this->assertSame(1, $repo['a']);

        $repo['b'] = 2;
        $this->assertSame(2, $repo['b']);

        unset($repo['b']);
        $this->assertFalse(isset($repo['b']));

        $repo[] = 'appended';
        $this->assertTrue($repo->has(0));
    }

    public function testCount(): void
    {
        $repo = new Repository(['a' => 1, 'b' => [1, 2]]);
        $this->assertSame(2, $repo->count());
        $this->assertSame(2, $repo->count('b'));
    }

    public function testIterator(): void
    {
        $repo = new Repository(['a' => 1, 'b' => 2]);
        $this->assertSame(['a', 'b'], array_keys(iterator_to_array($repo)));
    }

    public function testJsonSerialize(): void
    {
        $repo = new Repository(['a' => 1]);
        $this->assertSame(['a' => 1], $repo->jsonSerialize());
        $this->assertSame('{"a":1}', json_encode($repo));
    }
}
