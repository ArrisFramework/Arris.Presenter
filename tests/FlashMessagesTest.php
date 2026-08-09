<?php

namespace Tests;

use Arris\Presenter\FlashMessages;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FlashMessagesTest extends TestCase
{
    private ?array $savedSession = null;

    protected function setUp(): void
    {
        if (isset($_SESSION)) {
            $this->savedSession = $_SESSION;
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();

        if ($this->savedSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->savedSession;
        }
    }

    private function resetSingleton(): void
    {
        $prop = new \ReflectionProperty(FlashMessages::class, 'instance');
        $prop->setValue(null, null);
    }

    public function testGetInstanceWithSession(): void
    {
        $instance = FlashMessages::getInstance();
        $this->assertInstanceOf(FlashMessages::class, $instance);
        $this->assertSame($instance, FlashMessages::getInstance());
    }

    public function testGetInstanceWithoutSessionThrows(): void
    {
        unset($_SESSION);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session not found');
        FlashMessages::getInstance();
    }

    public function testConstructorWithInvalidStorageThrows(): void
    {
        $storage = 'not-an-array';
        $this->expectException(InvalidArgumentException::class);
        new FlashMessages($storage);
    }

    public function testConstructorWithArrayStorageByReference(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage, 'customKey');

        $flash->addMessage('info', 'Hello');
        $this->assertSame(['info' => ['Hello']], $storage['customKey']);
    }

    public function testAddMessageOneArgUsesDefaultKey(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $flash->addMessage('Just a message');
        $this->assertSame(['Just a message'], $storage['slimFlash'][FlashMessages::DEFAULT_KEY]);
    }

    public function testAddMessageWithKey(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $flash->addMessage('info', 'Hello');
        $flash->addMessage('info', 'World');

        $this->assertSame(['Hello', 'World'], $storage['slimFlash']['info']);
    }

    public function testAddMessageNow(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $flash->addMessageNow('notice', 'Now');

        $this->assertSame(['notice' => ['Now']], $flash->getMessages());
        $this->assertArrayNotHasKey('notice', $storage['slimFlash']);
    }

    public function testAddMessageNowOneArgUsesDefaultKey(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $flash->addMessageNow('Just now');
        $this->assertSame(['Just now'], $flash->getMessage(FlashMessages::DEFAULT_KEY));
    }

    public function testGetMessagesMergesPreviousAndNow(): void
    {
        $storage = [
            'slimFlash' => [
                'info' => ['fromPrevious'],
            ],
        ];
        $flash = new FlashMessages($storage);
        $flash->addMessageNow('info', 'fromNow');
        $flash->addMessageNow('other', 'OnlyNow');

        $this->assertSame([
            'info'  => ['fromPrevious', 'fromNow'],
            'other' => ['OnlyNow'],
        ], $flash->getMessages());
    }

    public function testStorageIsResetAfterConstruction(): void
    {
        $storage = ['slimFlash' => ['info' => ['old']]];
        $flash = new FlashMessages($storage);

        $this->assertSame([], $storage['slimFlash']);
        $this->assertSame(['old'], $flash->getMessage('info'));
    }

    public function testGetMessageDefault(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $this->assertNull($flash->getMessage('missing'));
        $this->assertSame('fallback', $flash->getMessage('missing', 'fallback'));
    }

    public function testGetFirstMessage(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $flash->addMessageNow('info', 'first');
        $flash->addMessageNow('info', 'second');

        $this->assertSame('first', $flash->getFirstMessage('info'));
        $this->assertSame('fallback', $flash->getFirstMessage('missing', 'fallback'));
    }

    public function testHasMessage(): void
    {
        $storage = [];
        $flash = new FlashMessages($storage);

        $this->assertFalse($flash->hasMessage('info'));
        $flash->addMessageNow('info', 'x');
        $this->assertTrue($flash->hasMessage('info'));
    }

    public function testClearMessages(): void
    {
        $storage = [
            'slimFlash' => ['info' => ['old']],
        ];
        $flash = new FlashMessages($storage);
        $flash->addMessageNow('info', 'new');

        $flash->clearMessages();

        $this->assertSame([], $flash->getMessages());
        $this->assertSame([], $storage['slimFlash']);
    }

    public function testClearMessage(): void
    {
        $storage = [
            'slimFlash' => ['info' => ['old'], 'other' => ['keep']],
        ];
        $flash = new FlashMessages($storage);
        $flash->addMessageNow('info', 'now');

        $flash->clearMessage('info');

        $this->assertFalse($flash->hasMessage('info'));
        $this->assertTrue($flash->hasMessage('other'));
    }
}
