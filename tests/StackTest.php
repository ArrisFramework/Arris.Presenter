<?php

namespace Tests;

use Arris\Presenter\Core\Stack;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StackTest extends TestCase
{
    public function testPushPopLifo(): void
    {
        $stack = new Stack();
        $stack->push(1, 2, 3);
        $this->assertSame(3, $stack->pop());
        $this->assertSame(2, $stack->pop());
        $this->assertSame(1, $stack->pop());
    }

    public function testConstructWithScalar(): void
    {
        $stack = new Stack('single');
        $this->assertSame(['single'], $stack->get());
    }

    public function testConstructWithArray(): void
    {
        $stack = new Stack(['a', 'b']);
        $this->assertSame(['a', 'b'], $stack->get());
    }

    public function testConstructWithEmpty(): void
    {
        $stack = new Stack();
        $this->assertTrue($stack->isEmpty());
    }

    public function testLimitThrows(): void
    {
        $stack = new Stack(null, 2);
        $stack->push('a', 'b');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stack is full!');
        $stack->push('c');
    }

    public function testPopFromEmptyThrows(): void
    {
        $stack = new Stack();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stack is empty');
        $stack->pop();
    }

    public function testAllowPopFromEmptyStack(): void
    {
        $stack = new Stack();
        $stack->allowPopFromEmptyStack(true, 'default');
        $this->assertSame('default', $stack->pop());
    }

    public function testAllowPopFromEmptyStackDefaultEmptyString(): void
    {
        $stack = new Stack();
        $stack->allowPopFromEmptyStack();
        $this->assertSame('', $stack->pop());
    }

    public function testIsEmptyCountClear(): void
    {
        $stack = new Stack(['a']);
        $this->assertFalse($stack->isEmpty());
        $this->assertSame(1, $stack->count());

        $stack->clear();
        $this->assertTrue($stack->isEmpty());
        $this->assertSame(0, $stack->count());
    }

    public function testGetGetReversedToArray(): void
    {
        $stack = new Stack(['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c'], $stack->get());
        $this->assertSame(['c', 'b', 'a'], $stack->getReversed());
        $this->assertSame(['c', 'b', 'a'], $stack->toArray());
    }

    public function testImplode(): void
    {
        $stack = new Stack(['a', 'b', 'c']);
        $this->assertSame('abc', $stack->implode());
        $this->assertSame('a|b|c', $stack->implode('|'));
        $this->assertSame('c|b|a', $stack->implode('|', true));
    }

    public function testReverse(): void
    {
        $stack = new Stack(['a', 'b', 'c']);
        $this->assertSame($stack, $stack->reverse());
        $this->assertSame(['c', 'b', 'a'], $stack->get());
    }

    public function testReverseEmptyStackIsNoop(): void
    {
        $stack = new Stack();
        $this->assertSame($stack, $stack->reverse());
        $this->assertTrue($stack->isEmpty());
    }
}
