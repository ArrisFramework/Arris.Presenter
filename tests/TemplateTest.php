<?php

namespace Tests;

use Arris\Entity\Result;
use Arris\Presenter\Breadcrumbs;
use Arris\Presenter\Core\Repository;
use Arris\Presenter\Headers;
use Arris\Presenter\Meta;
use Arris\Presenter\System\ContentType;
use Arris\Presenter\System\TemplateDefaultsBuilder;
use Arris\Presenter\Template;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SmartyException;

class TemplateTest extends TestCase
{
    private array $savedServer = [];

    protected function setUp(): void
    {
        self::resetDefaults();
        $this->savedServer = $_SERVER;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    }

    protected function tearDown(): void
    {
        self::resetDefaults();
        $_SERVER = $this->savedServer;
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

    private static function smartyOptionsOf(Template $t): Repository
    {
        $prop = new \ReflectionProperty(Template::class, 'smarty_options');
        return $prop->getValue($t);
    }

    private static function templateOptionsOf(Template $t): Repository
    {
        $prop = new \ReflectionProperty(Template::class, 'template_options');
        return $prop->getValue($t);
    }

    /* ---------- статические дефолты и фабрики ---------- */

    public function testContentTypeAliases(): void
    {
        $this->assertSame(ContentType::HTML, Template::CONTENT_TYPE_HTML);
        $this->assertSame(ContentType::JSON, Template::CONTENT_TYPE_JSON);
        $this->assertSame(ContentType::JSON_RAW, Template::CONTENT_TYPE_JSON_RAW);
        $this->assertSame(ContentType::RAW, Template::CONTENT_TYPE_RAW);
        $this->assertSame(ContentType::REDIRECT, Template::CONTENT_TYPE_REDIRECT);
        $this->assertSame(ContentType::NOT_FOUND, Template::CONTENT_TYPE_404);
        $this->assertSame(ContentType::INTERNAL_ERROR, Template::CONTENT_TYPE_500);
    }

    public function testSetDefaultsReturnsBuilder(): void
    {
        $this->assertInstanceOf(TemplateDefaultsBuilder::class, Template::setDefaults([]));
    }

    public function testSetDefaultsMergesFlatConfig(): void
    {
        Template::setDefaults([
            'setForceCompile' => true,
            'cleanup_extra_eol' => false,
            'custom_option' => 'value',
        ]);

        $t = Template::make();
        $this->assertTrue(self::smartyOptionsOf($t)->get('setForceCompile'));
        $this->assertFalse(self::templateOptionsOf($t)->get('cleanup_extra_eol'));
        $this->assertSame('value', self::templateOptionsOf($t)->get('custom_option'));
    }

    public function testApplyOptionRoutesSmartyKeys(): void
    {
        Template::applyOption('setTemplateDir', '/tpl');

        $t = Template::make();
        $this->assertSame('/tpl', self::smartyOptionsOf($t)->get('setTemplateDir'));
    }

    public function testApplyOptionRoutesOtherKeysToTemplateBag(): void
    {
        Template::applyOption('cleanup_extra_eol', false);

        $t = Template::make();
        $this->assertFalse(self::templateOptionsOf($t)->get('cleanup_extra_eol'));
    }

    public function testApplyOptionReservedKeysThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Template::applyOption('plugins', []);
    }

    public function testMakeReservedKeysThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Template::make(['hooks' => []]);
    }

    public function testMakeRoutesSmartyOptions(): void
    {
        $t = Template::make(['setTemplateDir' => '/tpl']);
        $this->assertSame('/tpl', self::smartyOptionsOf($t)->get('setTemplateDir'));
    }

    public function testMakeRendersFileOption(): void
    {
        $t = Template::make(['file' => 'string:made']);
        $this->assertSame('made', $t->renderToString());
    }

    public function testConstructorDefaultCleanupIsTrue(): void
    {
        $t = Template::make();
        $this->assertTrue(self::templateOptionsOf($t)->get('cleanup_extra_eol'));
    }

    /* ---------- настройка ---------- */

    public function testSetTemplateDirIsChainable(): void
    {
        $t = Template::make();
        $this->assertSame($t, $t->setTemplateDir('/dir'));
        $this->assertSame('/dir', self::smartyOptionsOf($t)->get('setTemplateDir'));
    }

    public function testSetEngineOptionIsChainable(): void
    {
        $t = Template::make();
        $this->assertSame($t, $t->setEngineOption('cleanup_extra_eol', false));
        $this->assertFalse(self::templateOptionsOf($t)->get('cleanup_extra_eol'));
    }

    public function testSetSmartyNativeOption(): void
    {
        $t = Template::make();
        $this->assertSame($t, $t->setSmartyNativeOption('escape_html', true));
        $this->assertTrue($t->smarty_custom_options['escape_html']);
    }

    public function testSetTemplateContent(): void
    {
        $t = Template::make();
        $t->assign('name', 'World');
        $t->setTemplateContent('Hello {$name}');
        $this->assertSame('Hello World', $t->renderToString());
    }

    public function testAddHeader(): void
    {
        $t = Template::make();
        $this->assertSame($t, $t->addHeader(Headers::X_FRAME_OPTIONS, 'DENY'));
        $this->assertTrue($t->headers->has('X-Frame-Options'));
    }

    /* ---------- регистрация ---------- */

    public function testRegisterPluginThrowsWhenNotCallable(): void
    {
        $t = Template::make();
        $this->expectException(SmartyException::class);
        $t->registerPlugin('function', 'bad', 'not_a_callable');
    }

    public function testRegisterPluginThrowsWhenCacheableWithAttr(): void
    {
        $t = Template::make();
        $this->expectException(SmartyException::class);
        $t->registerPlugin('function', 'p', 'strtoupper', true, 'cache_attr');
    }

    public function testRegisterClassThrowsWhenEmptyName(): void
    {
        $t = Template::make();
        $this->expectException(SmartyException::class);
        $t->registerClass('', 'Implementation');
    }

    public function testRegisterClassThrowsWhenEmptyImplementation(): void
    {
        $t = Template::make();
        $this->expectException(SmartyException::class);
        $t->registerClass('name', '');
    }

    public function testRegisterHookArrayForm(): void
    {
        $t = Template::make();
        $t->registerHook(['a' => static fn() => 'A', 'b' => static fn() => 'B']);

        $this->assertArrayHasKey('a', $t->hooks_engine->hooks);
        $this->assertArrayHasKey('b', $t->hooks_engine->hooks);
    }

    /* ---------- данные ---------- */

    public function testAssignSingleAndArray(): void
    {
        $t = Template::make();
        $t->assign('a', 1);
        $t->assign(['b' => 2, 'c' => 3]);

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $t->getAssignedTemplateVars());
    }

    public function testGetAssignedTemplateVars(): void
    {
        $t = Template::make();
        $t->assign('a', 1);

        $this->assertSame(1, $t->getAssignedTemplateVars('a'));
        $this->assertSame('', $t->getAssignedTemplateVars('missing'));
    }

    /* ---------- рендер-типы ---------- */

    public function testSetRenderTypeWithString(): void
    {
        $t = Template::make();
        $t->assignJSON(['a' => 1]);
        $t->setRenderType('json_raw');

        $this->assertSame(['a' => 1], json_decode($t->renderToString(), true));
    }

    public function testSetRenderTypeWithEnum(): void
    {
        $t = Template::make();
        $t->setRenderType(ContentType::HTML);
        $t->setTemplateContent('ok');

        $this->assertSame('ok', $t->renderToString());
    }

    public function testSetRenderTypeUnknownThrows(): void
    {
        $t = Template::make();
        $this->expectException(InvalidArgumentException::class);
        $t->setRenderType('bogus');
    }

    public function testAssignJSON(): void
    {
        $t = Template::make();
        $t->assignJSON(['a' => 1]);

        $decoded = json_decode($t->renderToString(), true);
        $this->assertTrue($decoded['is_success']);
        $this->assertSame(['a' => 1], $decoded['data']);
    }

    public function testJSONRaw(): void
    {
        $t = Template::make();
        $t->assignJSON(['a' => 1]);
        $t->setRenderType(Template::CONTENT_TYPE_JSON_RAW);

        $this->assertSame(['a' => 1], json_decode($t->renderToString(), true));
    }

    public function testSetJSONWithArray(): void
    {
        $t = Template::make();
        $t->setJSON(['foo' => 'bar']);

        $decoded = json_decode($t->renderToString(), true);
        $this->assertSame(['foo' => 'bar'], $decoded['data']);
    }

    public function testSetJSONWithObject(): void
    {
        $t = Template::make();
        $t->setJSON((object)['o' => 1]);

        $decoded = json_decode($t->renderToString(), true);
        $this->assertSame(['o' => 1], $decoded['data']);
    }

    public function testSetJSONWithResult(): void
    {
        $result = new Result();
        $result->setData(['k' => 'v']);

        $t = Template::make();
        $t->setJSON($result);

        $decoded = json_decode($t->renderToString(), true);
        $this->assertTrue($decoded['is_success']);
        $this->assertSame(['k' => 'v'], $decoded['data']);
    }

    public function testAssignResult(): void
    {
        $result = (new Result())->setData(['x' => 5]);

        $t = Template::make();
        $t->assignResult($result);

        $decoded = json_decode($t->renderToString(), true);
        $this->assertTrue($decoded['is_success']);
        $this->assertSame(['x' => 5], $decoded['data']);
    }

    public function testAssignRaw(): void
    {
        $t = Template::make();
        $t->assignRAW('<b>x</b>');
        $this->assertSame('<b>x</b>', $t->renderToString());
    }

    public function testAssignRawHTML(): void
    {
        $t = Template::make();
        $t->assignRawHTML('<b>x</b>');
        $this->assertSame('<b>x</b>', $t->renderToString());
    }

    /* ---------- хедеры и статусы ---------- */

    public function testRenderSetsContentTypeHeaderForHtml(): void
    {
        $t = Template::make();
        $t->setTemplateContent('x');
        $t->render();

        $this->assertSame('Content-Type: text/html; charset=utf-8', $t->headers->current_headers[0]['value']);
    }

    public function testRenderSetsContentTypeHeaderForJson(): void
    {
        $t = Template::make();
        $t->assignJSON(['a' => 1]);
        $t->render();

        $this->assertSame('Content-Type: application/json; charset=utf-8', $t->headers->current_headers[0]['value']);
    }

    public function testRenderSetsStatusHeaderFor404(): void
    {
        $t = Template::make();
        $t->setRenderType(Template::CONTENT_TYPE_404);
        $t->setTemplateContent('not found');
        $t->render();

        $this->assertSame('HTTP/1.1 404 Not Found', $t->headers->current_headers[0]['value']);
        $this->assertSame(404, $t->headers->current_headers[0]['code']);
    }

    public function testRenderSetsStatusHeaderFor500(): void
    {
        $t = Template::make();
        $t->setRenderType(Template::CONTENT_TYPE_500);
        $t->setTemplateContent('error');
        $t->render();

        $this->assertSame('HTTP/1.1 500 Internal Server Error', $t->headers->current_headers[0]['value']);
    }

    public function testRenderRedirectSuppressesHeaders(): void
    {
        $t = Template::make();
        $t->setRedirect('/target', 302);

        $this->assertSame('', $t->renderToString());
        $this->assertFalse($t->headers->headers_present);
    }

    public function testSetRedirect(): void
    {
        $t = Template::make();
        $this->assertSame($t, $t->setRedirect('/target', 302));

        $this->assertTrue($t->isRedirect());
        $this->assertSame(ContentType::REDIRECT, self::renderTypeOf($t));
    }

    private static function renderTypeOf(Template $t): ContentType
    {
        $prop = new \ReflectionProperty(Template::class, 'render_type');
        return $prop->getValue($t);
    }

    /* ---------- cleanup_extra_eol ---------- */

    public function testCleanupExtraEolEnabledByDefault(): void
    {
        $t = Template::make();
        $t->setTemplateContent("Hello\n\n\nWorld");

        $this->assertSame("Hello\nWorld", $t->renderToString());
    }

    public function testCleanupExtraEolDisabled(): void
    {
        $t = Template::make(['cleanup_extra_eol' => false]);
        $t->setTemplateContent("Hello\n\n\nWorld");

        $this->assertSame("Hello\n\n\nWorld", $t->renderToString());
    }

    public function testCleanupRemovesWhitespaceOnlyLines(): void
    {
        $t = Template::make();
        $t->setTemplateContent("a\n\t \nb");

        $this->assertSame("a\nb", $t->renderToString());
    }

    /* ---------- хуки в шаблоне ---------- */

    public function testHookInTemplate(): void
    {
        $t = Template::make();
        $t->registerHook('greet', static fn(string $name): string => "Hi {$name}!");
        $t->setTemplateContent('{hook run="greet" name="Sam"}');

        $this->assertSame('Hi Sam!', $t->renderToString());
    }

    public function testHookAssignInTemplate(): void
    {
        $t = Template::make();
        $t->registerHook('calc', static fn(int $a, int $b): int => $a + $b);
        $t->setTemplateContent('{hook run="calc" a=2 b=3 assign="res"}{$res}');

        $this->assertSame('5', $t->renderToString());
    }

    /* ---------- fork / clean ---------- */

    public function testForkResetsState(): void
    {
        $t = Template::make();
        $t->assign('v', 'old');
        $t->setTemplateContent('tpl');
        $t->setRenderType(Template::CONTENT_TYPE_JSON);

        $child = $t->fork();

        $this->assertNotSame($t, $child);
        $this->assertSame([], $child->getAssignedTemplateVars());
        $this->assertSame(ContentType::HTML, self::renderTypeOf($child));
        $this->assertSame('', (string)$child->renderToString());
    }

    public function testForkKeepsHooksAndConfiguration(): void
    {
        $t = Template::make();
        $t->registerHook('greet', static fn() => 'hi');
        $t->setTemplateDir('/dir');

        $child = $t->fork();

        $this->assertArrayHasKey('greet', $child->hooks_engine->hooks);
        $this->assertSame('/dir', self::smartyOptionsOf($child)->get('setTemplateDir'));
    }

    public function testForkFreshMetaAndHeaders(): void
    {
        $t = Template::make();
        $t->meta->setTitle('Old');
        $t->headers->add(Headers::CONTENT_TYPE, 'text/html');

        $child = $t->fork();

        $this->assertSame('', $child->meta->toArray()['title']);
        $this->assertTrue($child->headers->isEmpty());
        $this->assertSame('Old', $t->meta->toArray()['title']);
    }

    public function testCleanResetsAssignedVars(): void
    {
        $t = Template::make();
        $t->assign('foo', 1);
        $t->setTemplateContent('{$foo}');

        $this->assertSame('1', $t->render(false, true));
        $this->assertSame([], $t->getAssignedTemplateVars());
    }

    /* ---------- подключенные сервисы ---------- */

    public function testWiredServices(): void
    {
        $t = Template::make();

        $this->assertInstanceOf(Headers::class, $t->headers);
        $this->assertInstanceOf(Breadcrumbs::class, $t->breadcrumbs);
        $this->assertInstanceOf(Meta::class, $t->meta);
        $this->assertInstanceOf(\Arris\Presenter\Hooks::class, $t->hooks_engine);
    }
}
