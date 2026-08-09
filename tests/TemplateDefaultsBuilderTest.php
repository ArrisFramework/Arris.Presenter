<?php

namespace Tests;

use Arris\Presenter\System\TemplateDefaultsBuilder;
use Arris\Presenter\Template;
use PHPUnit\Framework\TestCase;

class TemplateDefaultsBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        self::resetDefaults();
    }

    protected function tearDown(): void
    {
        self::resetDefaults();
    }

    private static function resetDefaults(): void
    {
        $prop = new \ReflectionProperty(Template::class, 'default_options');
        $prop->setValue(null, [
            'smarty_options'   => [],
            'template_options' => [],
            'plugins'          => [],
            'classes'          => [],
            'hooks'            => [],
        ]);
    }

    public function testSetDefaultsReturnsBuilder(): void
    {
        $builder = Template::setDefaults(['cleanup_extra_eol' => false]);
        $this->assertInstanceOf(TemplateDefaultsBuilder::class, $builder);
    }

    public function testRegisterPluginIsChainable(): void
    {
        $builder = Template::setDefaults();
        $this->assertSame($builder, $builder->registerPlugin('function', 'up', 'strtoupper'));

        $tpl = Template::make();
        $this->assertArrayHasKey('up', $tpl->smarty_plugins);
        $this->assertSame('function', $tpl->smarty_plugins['up'][0]);
    }

    public function testRegisterClassIsChainable(): void
    {
        $builder = Template::setDefaults();
        $this->assertSame($builder, $builder->registerClass('demo', self::class));

        $tpl = Template::make();
        $this->assertSame('demo', $tpl->smarty_classes['demo']['name']);
        $this->assertSame(self::class, $tpl->smarty_classes['demo']['impl']);
    }

    public function testRegisterHookIsChainable(): void
    {
        $callback = static fn() => 'x';
        $builder = Template::setDefaults();
        $this->assertSame($builder, $builder->registerHook('h', $callback));

        $tpl = Template::make();
        $this->assertArrayHasKey('h', $tpl->hooks_engine->hooks);
    }
}
