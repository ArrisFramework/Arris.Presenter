<?php

namespace Tests;

use Arris\Presenter\Core\Repository;
use Arris\Presenter\Hooks;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class HooksCallbackDemo
{
    public function shout(string $word): string
    {
        return strtoupper($word);
    }

    public static function stat(): string
    {
        return 'STATIC';
    }

    public function __invoke(): string
    {
        return 'INVOKE';
    }
}

class HooksTest extends TestCase
{
    private Hooks $hooks;

    protected function setUp(): void
    {
        $this->hooks = new Hooks(new Repository(), new NullLogger());
    }

    private function createTemplateMock(): \Smarty_Internal_Template
    {
        $smarty = new \Smarty();
        return $smarty->createTemplate('string:');
    }

    private function captureWarning(callable $fn): array
    {
        $captured = [];
        set_error_handler(static function (int $severity, string $message) use (&$captured): bool {
            $captured[] = $message;
            return true;
        });
        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }
        return [$result, $captured];
    }

    public function testConstructorReadsOptions(): void
    {
        $hooks = new Hooks(new Repository([
            'hook_disable_named_params' => true,
            'ignore_undefined_hooks'    => false,
        ]));

        $this->assertTrue($hooks->disable_named_params);
        $this->assertFalse($hooks->ignore_undefined_hooks);
    }

    public function testConstructorDefaults(): void
    {
        $this->assertFalse($this->hooks->disable_named_params);
        $this->assertTrue($this->hooks->ignore_undefined_hooks);
        $this->assertSame([], $this->hooks->hooks);
    }

    public function testRegisterHookWithoutPriority(): void
    {
        $callback = static fn() => 'x';
        $this->assertSame($this->hooks, $this->hooks->registerHook('name', $callback));

        $this->assertSame([0 => $callback], $this->hooks->hooks['name']);
    }

    public function testRegisterHookWithPriority(): void
    {
        $callback = static fn() => 'x';
        $this->hooks->registerHook('name', $callback, 100);

        $this->assertSame([100 => $callback], $this->hooks->hooks['name']);
    }

    public function testRegisterHooksNumericForm(): void
    {
        $callback = static fn() => 'x';
        $this->hooks->registerHooks([
            ['name', $callback, 100],
        ]);

        $this->assertSame([100 => $callback], $this->hooks->hooks['name']);
    }

    public function testRegisterHooksNamedForm(): void
    {
        $callback = static fn() => 'x';
        $this->hooks->registerHooks([
            ['name' => 'n', 'callback' => $callback, 'priority' => 50],
        ]);

        $this->assertSame([50 => $callback], $this->hooks->hooks['n']);
    }

    public function testRegisterHooksSkipsShortDefinitions(): void
    {
        $this->hooks->registerHooks([
            ['only-name'],
        ]);

        $this->assertSame([], $this->hooks->hooks);
    }

    public function testRunMissingRunParam(): void
    {
        [$result, $warnings] = $this->captureWarning(
            fn() => $this->hooks->run([], $this->createTemplateMock())
        );

        $this->assertSame('', $result);
        $this->assertStringContainsString("missing 'run'", $warnings[0]);
    }

    public function testRunUndefinedHookIgnored(): void
    {
        [$result, $warnings] = $this->captureWarning(
            fn() => $this->hooks->run(['run' => 'nope'], $this->createTemplateMock())
        );

        $this->assertSame('', $result);
        $this->assertSame([], $warnings);
    }

    public function testRunUndefinedHookNotIgnored(): void
    {
        $hooks = new Hooks(new Repository(['ignore_undefined_hooks' => false]));

        [$result, $warnings] = $this->captureWarning(
            fn() => $hooks->run(['run' => 'nope'], $this->createTemplateMock())
        );

        $this->assertSame('', $result);
        $this->assertStringContainsString('nope not defined', $warnings[0]);
    }

    public function testRunSingleClosureCallback(): void
    {
        $this->hooks->registerHook('greet', static fn(string $name): string => "Hi {$name}!");

        $result = $this->hooks->run(['run' => 'greet', 'name' => 'Sam'], $this->createTemplateMock());
        $this->assertSame('Hi Sam!', $result);
    }

    public function testRunChainOrderedByPriorityDesc(): void
    {
        $this->hooks->registerHook('chain', static fn() => 'A', 10);
        $this->hooks->registerHook('chain', static fn() => 'B', 100);
        $this->hooks->registerHook('chain', static fn() => 'C', 50);

        $result = $this->hooks->run(['run' => 'chain'], $this->createTemplateMock());
        $this->assertSame('BCA', $result);
    }

    public function testRunNamedParams(): void
    {
        $this->hooks->registerHook('swap', static fn(string $b, string $a): string => $a . $b);

        $result = $this->hooks->run(['run' => 'swap', 'a' => 'A', 'b' => 'B'], $this->createTemplateMock());
        $this->assertSame('AB', $result);
    }

    public function testRunWithDisabledNamedParams(): void
    {
        $hooks = new Hooks(new Repository(['hook_disable_named_params' => true]));
        $hooks->registerHook('swap', static fn(string $b, string $a): string => $a . $b);

        $result = $hooks->run(['run' => 'swap', 'a' => 'A', 'b' => 'B'], $this->createTemplateMock());
        $this->assertSame('BA', $result);
    }

    public function testRunAssign(): void
    {
        $this->hooks->registerHook('greet', static fn(string $word): string => strtoupper($word));

        $tpl = $this->createTemplateMock();
        $result = $this->hooks->run(['run' => 'greet', 'word' => 'hi', 'assign' => 'collected'], $tpl);

        $this->assertSame('', $result);
        $this->assertSame('HI', $tpl->getTemplateVars('collected'));
    }

    public function testRunCallbackInstanceArray(): void
    {
        $this->hooks->registerHook('h', [HooksCallbackDemo::class, 'shout']);
        $this->assertSame('HI', $this->hooks->run(['run' => 'h', 'word' => 'hi'], $this->createTemplateMock()));
    }

    public function testRunCallbackAtForm(): void
    {
        $this->hooks->registerHook('h', HooksCallbackDemo::class . '@shout');
        $this->assertSame('HI', $this->hooks->run(['run' => 'h', 'word' => 'hi'], $this->createTemplateMock()));
    }

    public function testRunCallbackStaticForm(): void
    {
        $this->hooks->registerHook('h', HooksCallbackDemo::class . '::stat');
        $this->assertSame('STATIC', $this->hooks->run(['run' => 'h'], $this->createTemplateMock()));
    }

    public function testCurrentHookContext(): void
    {
        $this->hooks->registerHook('ctx', static function (): string {
            return Hooks::$current_hook_name
                . Hooks::$current_hook_priority
                . (is_callable(Hooks::$current_hook_callback) ? 'callable' : 'not');
        }, 42);

        $result = $this->hooks->run(['run' => 'ctx'], $this->createTemplateMock());
        $this->assertSame('ctx42callable', $result);
    }

    public function testDisableNamedParamsIsChainable(): void
    {
        $this->assertSame($this->hooks, $this->hooks->disableNamedParams(true));
        $this->assertTrue($this->hooks->disable_named_params);
    }

    public function testSetAutoResolveMethod(): void
    {
        $callable = static fn() => null;
        $this->assertSame($this->hooks, $this->hooks->setAutoResolveMethod($callable));
        $this->assertSame($callable, $this->hooks->auto_resolve_method);
    }
}
