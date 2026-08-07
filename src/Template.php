<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Helper;
use Arris\Presenter\Core\Repository;
use Arris\Presenter\System\ContentType;
use Arris\Presenter\System\TemplateDefaultsBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smarty;
use SmartyException;
use Arris\Entity\Result;
use function json_encode;
use function preg_replace;

class Template implements TemplateInterface
{
    /**
     * Типы данных для рендера.
     * Алиасы на кейсы enum ContentType (Arris\Presenter\System) для обратной совместимости.
     */
    public const CONTENT_TYPE_RSS      = ContentType::RSS;
    public const CONTENT_TYPE_XML      = ContentType::XML;

    public const CONTENT_TYPE_JSON     = ContentType::JSON;
    public const CONTENT_TYPE_JSON_RAW = ContentType::JSON_RAW;

    public const CONTENT_TYPE_HTML     = ContentType::HTML;

    public const CONTENT_TYPE_JS       = ContentType::JS;
    public const CONTENT_TYPE_JS_RAW   = ContentType::JS_RAW;

    public const CONTENT_TYPE_RAW      = ContentType::RAW;
    public const CONTENT_TYPE_RAW_HTML = ContentType::RAW_HTML;

    public const CONTENT_TYPE_RESULT   = ContentType::RESULT;

    public const CONTENT_TYPE_REDIRECT = ContentType::REDIRECT;

    public const CONTENT_TYPE_404      = ContentType::NOT_FOUND;
    public const CONTENT_TYPE_500      = ContentType::INTERNAL_ERROR;

    /**
     * Smarty instance
     *
     * @var Smarty|null
     */
    private ?Smarty $smarty = null;

    /**
     * Smarty Options for deferred init
     *
     * @var Repository
     */
    private Repository $smarty_options;

    /**
     * @var Repository
     */
    private Repository $template_options;

    /**
     * Smarty Plugins for deferred init
     *
     * @var array
     */
    public array $smarty_plugins = [];

    /**
     * Classes for deferred init
     *
     * @var array
     */
    public array $smarty_classes = [];

    /**
     * Фильтры для отложенной инициализации.
     * Используется для loadFilter()
     *
     * @var array
     */
    public array $smarty_filters = [];

    /**
     * Кастомные опции Smarty для ленивой инициализации
     *
     * @var array
     */
    public array $smarty_custom_options = [];

    /**
     * Опции редиректа
     *
     * @var array
     */
    private array $redirect_options = [
        '_'         =>  false,      // Флаг "инициирован ли редирект?"
        'target'    =>  '',         // Куда
        'code'      =>  301         // С каким кодом?
    ];

    /**
     * Файл глобального шаблона
     *
     * @var string
     */
    private string $template_file = '';

    /**
     * Тип рендера, по умолчанию - HTML
     *
     * @var ContentType
     */
    private ContentType $render_type = ContentType::HTML;

    /**
     * Assigned-переменные шаблона
     *
     * @var array
     */
    private array $assigned_template_vars = [];

    /**
     * Assigned сырой контент, который надо отдать в as-is, без smarty-рендера
     *
     * @var string
     */
    private string $assigned_raw_content = '';

    /**
     * Assigned result content
     *
     * @var Result|null
     */
    private ?Result $assigned_result;

    /**
     * Assigned JSON content
     *
     * @var Result|null
     */
    private ?Result $assigned_json;

    /**
     * @var Headers
     */
    public Headers $headers;

    /**
     * @var LoggerInterface|null
     */
    public $logger = null;

    public Breadcrumbs $breadcrumbs;

    public Hooks $hooks_engine;

    /**
     * Ключи, уходящие в smarty-корзину при плоской маршрутизации setDefaults()/make()
     */
    private const SMARTY_OPTION_KEYS = [
        'setTemplateDir',
        'setCompileDir',
        'setForceCompile',
        'setConfigDir',
    ];

    /**
     * Статические дефолты для всех инстансов.
     * Ключи: smarty_options, template_options, plugins, classes, hooks
     *
     * @var array
     */
    private static array $default_options = [
        'smarty_options'   => [],
        'template_options' => [],
        'plugins'          => [],
        'classes'          => [],
        'hooks'            => [],
    ];

    /**
     * Устанавливает статические дефолты (мержит в существующие).
     * Принимает ПЛОСКИЙ массив опций: ключи setTemplateDir/setCompileDir/setForceCompile/setConfigDir
     * маршрутизируются в smarty-корзину, ключи plugins/classes/hooks зарезервированы
     * (для них есть addPlugin()/addClass()/addHook()), остальное - в template-корзину.
     *
     * Возвращает fluent-билдер для цепочки регистраций:
     * Template::setDefaults([...])->registerClass(...)->registerHook(...)->registerPlugin(...)
     *
     * @param array $config
     * @return TemplateDefaultsBuilder
     */
    public static function setDefaults(array $config = []): TemplateDefaultsBuilder
    {
        foreach ($config as $key => $value) {
            self::applyOption($key, $value);
        }

        return new TemplateDefaultsBuilder();
    }

    /**
     * Точечно обновляет один дефолт-опшн
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws \InvalidArgumentException
     */
    public static function applyOption(string $name, mixed $value): void
    {
        if (\in_array($name, self::SMARTY_OPTION_KEYS, true)) {
            self::$default_options['smarty_options'][ $name ] = $value;
            return;
        }

        if (\in_array($name, ['plugins', 'classes', 'hooks'], true)) {
            throw new \InvalidArgumentException("'{$name}' is reserved: use addPlugin()/addClass()/addHook()");
        }

        self::$default_options['template_options'][ $name ] = $value;
    }

    /**
     * Добавляет плагин в дефолтные регистрации (аддитивно)
     *
     * @param string $type
     * @param string $name
     * @param $callback
     * @param bool $cacheable
     * @param null $cache_attr
     * @return void
     */
    public static function addPlugin(string $type, string $name, $callback, bool $cacheable = false, $cache_attr = null): void
    {
        self::$default_options['plugins'][] = [ $type, $name, $callback, $cacheable, $cache_attr ];
    }

    /**
     * Добавляет класс в дефолтные регистрации (аддитивно)
     *
     * @param string $name
     * @param string $implementation
     * @return void
     */
    public static function addClass(string $name, string $implementation): void
    {
        self::$default_options['classes'][ $name ] = $implementation;
    }

    /**
     * Добавляет хук в дефолтные регистрации (аддитивно)
     *
     * @param string $hook_name
     * @param $hook_callback
     * @return void
     */
    public static function addHook(string $hook_name, $hook_callback): void
    {
        self::$default_options['hooks'][ $hook_name ][] = $hook_callback;
    }

    /**
     * Фабрика: чистый инстанс на основе статических дефолтов.
     * Принимает ПЛОСКИЙ массив опций (маршрутизация как в setDefaults()).
     * Опции инстанса имеют приоритет над дефолтами.
     *
     * @param array $options
     * @param $logger
     * @return static
     */
    public static function make(array $options = [], $logger = null): static
    {
        [$template_options, $smarty_options] = self::splitOptions($options);

        return new static($template_options, $smarty_options, $logger);
    }

    /**
     * Разделяет плоский массив опций на template/smarty корзины
     *
     * @param array $options
     * @return array
     * @throws \InvalidArgumentException
     */
    private static function splitOptions(array $options): array
    {
        $smarty_options   = [];
        $template_options = [];

        foreach ($options as $key => $value) {
            if (\in_array($key, self::SMARTY_OPTION_KEYS, true)) {
                $smarty_options[ $key ] = $value;
            } elseif (\in_array($key, ['plugins', 'classes', 'hooks'], true)) {
                throw new \InvalidArgumentException("'{$key}' is reserved: use addPlugin()/addClass()/addHook()");
            } else {
                $template_options[ $key ] = $value;
            }
        }

        return [ $template_options, $smarty_options ];
    }

    /**
     * Создает дочерний инстанс на основе текущего: наследует конфигурацию,
     * плагины, классы и хуки, но полностью сбрасывает состояние (шаблон, assigned,
     * хедеры, редирект, рендер-тип). Smarty реинициализируется лениво.
     *
     * @return static
     */
    public function fork(): static
    {
        $clone = clone $this;

        $clone->smarty = null;

        $clone->smarty_options   = clone $this->smarty_options;
        $clone->template_options = clone $this->template_options;

        $clone->headers      = new Headers($this->logger);
        $clone->breadcrumbs  = new Breadcrumbs($this->logger);
        $clone->hooks_engine = clone $this->hooks_engine;

        $clone->assigned_template_vars = [];
        $clone->assigned_raw_content   = '';
        $clone->assigned_result        = null;
        $clone->assigned_json          = new Result();

        $clone->redirect_options = [
            '_'     =>  false,
            'target'=>  '',
            'code'  =>  301
        ];

        $clone->template_file = '';
        $clone->render_type   = ContentType::HTML;

        return $clone;
    }

    /**
     * Алиас для fork(): историческое имя метода
     *
     * @return static
     */
    public function spawn(): static
    {
        return $this->fork();
    }

    /**
     * Рендер в строку без отправки хедеров и вывода
     *
     * @return string
     */
    public function renderToString(): string
    {
        return (string)$this->render(false);
    }

    /**
     * Конструктор презентера.
     * Применяет статические дефолты, затем аргументы инстанса
     *
     * @param array $template_options
     * @param array $smarty_options
     * @param $logger
     *
     * @throws SmartyException
     */
    public function __construct(array $template_options = [], array $smarty_options = [], $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();

        $template_options = \array_merge(self::$default_options['template_options'], $template_options);
        $smarty_options   = \array_merge(self::$default_options['smarty_options'],   $smarty_options);

        $this->smarty_options   = new Repository($smarty_options);
        $this->template_options = new Repository($template_options);

        $this->headers      = new Headers($this->logger);
        $this->breadcrumbs  = new Breadcrumbs($this->logger);
        $this->hooks_engine = new Hooks($this->template_options, $this->logger);

        foreach (self::$default_options['plugins'] as $plugin) {
            $this->registerPlugin($plugin[0], $plugin[1], $plugin[2], $plugin[3] ?? false, $plugin[4] ?? null);
        }

        foreach (self::$default_options['classes'] as $name => $impl) {
            $this->registerClass($name, $impl);
        }

        foreach (self::$default_options['hooks'] as $hook_name => $callbacks) {
            foreach ($callbacks as $callback) {
                $this->hooks_engine->registerHook($hook_name, $callback);
            }
        }

        if (\array_key_exists('file', $template_options)) {
            $this->setTemplate($template_options['file']);
        }

        if (\array_key_exists('source', $template_options)) {
            $this->setTemplate($template_options['source']);
        }

        if (\array_key_exists('source', $template_options)) {
            $this->setTemplate($template_options['source']);
        }

        // удалять ли лишние переводы строк при рендере?
        $this->setEngineOption(
            'cleanup_extra_eol',
            $template_options['cleanup_extra_eol'] ?? true
        );

        // assigned_template_vars — намеренно plain-array, а не Repository:
        // это плоский баг данных для Smarty (запись assign() + foreach в renderTemplate),
        // без слияния дефолтов, ключи которого нужны только внутри Template.
        // Repository оправдан для конфигурационных корзин (smarty_options/template_options).

        $this->assigned_json = new Result();
    }

    /**
     * Устанавливает каталог шаблонов SMARTY
     *
     * @param string $dir
     * @return $this
     */
    public function setTemplateDir(string $dir):Template
    {
        $this->smarty_options->set('setTemplateDir', $dir);
        return $this;
    }

    /**
     * Устанавливает каталог скомпилированных шаблонов
     *
     * @param string $dir
     * @return $this
     */
    public function setCompileDir(string $dir):Template
    {
        $this->smarty_options->set('setCompileDir', $dir);
        return $this;
    }

    /**
     * Устанавливает флаг принудительной компиляции шаблонов при каждом запросе
     *
     * @param bool $force_compile
     * @return $this
     */
    public function setForceCompile(bool $force_compile):Template
    {
        $this->smarty_options->set('setForceCompile', $force_compile);

        return $this;
    }

    /**
     * @param string $config_dir
     * @return $this
     */
    public function setConfigDir(string $config_dir):Template
    {
        $this->smarty_options->set('setConfigDir', $config_dir);
        return $this;
    }

    /**
     * Регистрирует плагин для поздней инициализации
     *
     * @param string $type
     * @param string $name
     * @param $callback
     * @param bool $cacheable
     * @param null $cache_attr
     * @return $this
     * @throws SmartyException
     */
    public function registerPlugin(string $type, string $name, $callback, bool $cacheable = false, $cache_attr = null):Template
    {
        if (!\is_callable($callback)) {
            throw new SmartyException("Plugin '{$name}' not callable");
        }

        if ($cacheable && $cache_attr) {
            throw new SmartyException("Cannot set caching attributes for plugin '{$name}' when it is cacheable.");
        }

        $this->smarty_plugins[ $name ] = [
            TemplateInterface::INDEX_PLUGIN_TYPE        => $type,
            TemplateInterface::INDEX_PLUGIN_NAME        => $name,
            TemplateInterface::INDEX_PLUGIN_CALLBACK    => $callback,
            TemplateInterface::INDEX_PLUGIN_CACHEABLE   => $cacheable,
            TemplateInterface::INDEX_PLUGIN_CACHEATTR   => $cache_attr
        ];
        return $this;
    }

    /**
     * Регистрируем класс для поздней инициализации
     *
     * @param string $name
     * @param string $implementation
     * @return $this
     * @throws SmartyException
     */
    public function registerClass(string $name, string $implementation):Template
    {
        if (empty($name)) {
            throw new SmartyException("Can't register class with empty name");
        }

        if (empty($implementation)) {
            throw new SmartyException("Can't register class {$name} with empty implementation");
        }

        $this->smarty_classes[ $name ] = [
            'name'  =>  $name,
            'impl'  =>  $implementation
        ];

        return $this;
    }

    /**
     * Задает значение "нативной" опции Smarty для поздней инициализации.
     * Это значение будет установлено напрямую полю объекта Smarty
     *
     * https://www.smarty.net/docsv2/ru/language.escaping.tpl
     *
     * @param $key_name
     * @param $key_value
     * @return $this
     */
    public function setSmartyNativeOption($key_name, $key_value):Template
    {
        $this->smarty_custom_options[$key_name] = $key_value;

        return $this;
    }

    /**
     * Устанавливает опцию шаблонизатора
     *
     * @param $key_name
     * @param $key_value
     * @return $this
     */
    public function setEngineOption($key_name, $key_value = null):Template
    {
        $this->template_options[$key_name] = $key_value;

        return $this;
    }

    public function registerHook($hook, $hook_callback = null, ?int $priority = null):Template
    {
        if (is_array($hook)) {
            foreach ($hook as $name => $callback) {
                $this->registerHook($name, $callback);
            }
        } else {
            $this->hooks_engine->registerHook($hook, $hook_callback, $priority);
        }

        return $this;
    }

    /**
     * Поздняя инициализация Smarty
     *
     * @return void
     * @throws SmartyException
     */
    private function initSmarty(): void
    {
        $this->smarty = new Smarty();

        if ($this->smarty_options->has('setTemplateDir')) {
            $this->smarty->setTemplateDir( $this->smarty_options->get('setTemplateDir'));
        }

        if ($this->smarty_options->has('setCompileDir')) {
            $this->smarty->setCompileDir( $this->smarty_options->get('setCompileDir'));
        }

        if ($this->smarty_options->has('setForceCompile')) {
            $this->smarty->setForceCompile( $this->smarty_options->get('setForceCompile'));
        }

        if ($this->smarty_options->has('setConfigDir')) {
            $this->smarty->setConfigDir( $this->smarty_options->get('setConfigDir'));
        }

        /*
         * PHP8: activate convert warnings about undefined or null template vars -> to notices
         */
        $this->smarty->muteUndefinedOrNullWarnings();

        // $SMARTY->setErrorReporting(E_ALL & ~E_NOTICE & ~E_USER_DEPRECATED); ?

        foreach ($this->smarty_custom_options as $key => $value) {
            $this->smarty->{$key} = $value;
        }

        // $this->hooks_engine = new Hooks($this->template_options, $this->logger);

        $this->smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'hook', [ $this->hooks_engine, 'run'], false);

        /**
         * Register plugins
         */
        foreach ($this->smarty_plugins as $plugin) {
            $this->smarty->registerPlugin(
                $plugin[ TemplateInterface::INDEX_PLUGIN_TYPE ],
                $plugin[ TemplateInterface::INDEX_PLUGIN_NAME],
                $plugin[ TemplateInterface::INDEX_PLUGIN_CALLBACK ],
                $plugin[ TemplateInterface::INDEX_PLUGIN_CACHEABLE ],
                $plugin[ TemplateInterface::INDEX_PLUGIN_CACHEATTR ]
            );
        }

        foreach ($this->smarty_classes as $class) {
            $this->smarty->registerClass($class['name'], $class['impl']);
        }
    }

    /**
     * Проверяет, инициирован ли редирект?
     *
     * @return bool
     */
    public function isRedirect():bool
    {
        return $this->redirect_options['_'];
    }

    /**
     * Собственно, выполняет редирект на основе установленных параметров
     *
     * @param string|null $uri
     * @param int|null $code
     * @param bool $replace_headers
     * @return false|void
     */
    public function makeRedirect(string $uri = null, int $code = null, bool $replace_headers = true)
    {
        if (false === $this->isRedirect()) {
            return false;
        }

        $_uri
            = \is_null($uri)
            ? (
                \array_key_exists('uri', $this->redirect_options)
                    ? $this->redirect_options['uri']
                    : null
            )
            : $uri;

        $_code
            = \is_null($code)
            ? (
                \array_key_exists('code', $this->redirect_options)
                    ? $this->redirect_options['code']
                    : null
            )
            : $code;

        if (empty($_uri)) {
            return false;
        }

        if (empty($_code)) {
            $_code = 200;
        }

        if ( \preg_match('/^https?:\/\//i', $_uri) === 1 ) {
            $location = $_uri;
        } else {
            $scheme = Helper::is_ssl() ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $location = "{$scheme}://{$host}{$_uri}";
        }

        $this->headers->add(Headers::LOCATION, $location, $replace_headers, $_code);
        $this->headers->send();

        exit(0);
    }

    /**
     * Устанавливает файл шаблона
     *
     * @param string $filename
     * @return $this
     */
    public function setTemplate(string $filename = ''): Template
    {
        $this->template_file = empty($filename) ? '' : $filename;
        return $this;
    }

    /**
     * Устанавливает шаблон из строки, аналогично вызову `setTemplate('string:' . $data)`
     *
     * $templateContent = 'Привет, {$name}!';
     * ->setTemplate('string:' . $templateContent)
     * Важно: строка не кэшируется
     *
     * @param string $content
     * @return $this
     */
    public function setTemplateContent(string $content):Template
    {
        $this->template_file = 'string:' . $content;
        return $this;
    }

    /**
     * Возвращает данные, проброшенные в шаблон
     *
     * @param $varName
     * @return array|mixed|string
     */
    public function getAssignedTemplateVars($varName = null)
    {
        return
            $varName
                ? ( \array_key_exists($varName, $this->assigned_template_vars) ? $this->assigned_template_vars[$varName] : '')
                : $this->assigned_template_vars;
    }

    /**
     * Полная очистка данных.
     * Очищает данные, переданные в шаблон и в инстанс Smarty
     *
     * @param bool $clear_cache
     * @return bool
     */
    public function clean(bool $clear_cache = true): bool
    {
        $this->assigned_template_vars = [];
        $this->assigned_result = null;
        $this->assigned_json = new Result();
        $this->assigned_raw_content = '';

        if (!$clear_cache) {
            return true;
        }

        if ($this->smarty instanceof Smarty) {
            foreach ($this->smarty->getTemplateVars() as $k => $v) {
                $this->smarty->clearCache($k);
            }

            $this->smarty->clearAllCache();
            $this->smarty->clearAllAssign();
        }

        return true;
    }

    /**
     * Assign Template variables
     *
     * @param $key
     * @param $value
     * @return void
     */
    public function assign($key, $value = null): void
    {
        if (\is_array($key)) {
            foreach ($key as $k => $v) {
                $this->assign($k, $v);
            }
        } else {
            $this->assigned_template_vars[ $key ] = $value;
        }
    }

    /**
     * Устанавливает сырое значение
     *
     * @param string $html
     * @return void
     */
    public function assignRAW(string $html):void
    {
        $this->assigned_raw_content = $html;
        $this->setRenderType( Template::CONTENT_TYPE_RAW );
    }

    public function assignRawHTML(string $html):void
    {
        $this->assigned_raw_content = $html;
        $this->setRenderType( Template::CONTENT_TYPE_RAW );
    }

    /**
     * Добавляет значения в JSON-набор данных
     *
     * @param array $json
     * @return void
     */
    public function assignJSON(array $json): void
    {
        $this->assigned_json->setData($json);
        $this->setRenderType( Template::CONTENT_TYPE_JSON );
    }

    /**
     * Устанавливает значение JSON-набора данных напрямую
     *
     * @param $json
     * @return void
     */
    public function setJSON($json):void
    {
        if (\is_array($json)) {
            $this->assigned_json->setData($json);
        } elseif (\is_object($json)) {
            $this->assigned_json->setData( (array)$json);
        } elseif ($json instanceof Result) {
            $this->assigned_json = $json;
        }
        $this->setRenderType( Template::CONTENT_TYPE_JSON );
    }

    /**
     * Устанавливает значение набора данных Result
     *
     * @param Result $result
     * @return void
     */
    public function assignResult(Result $result)
    {
        $this->assigned_result = $result;
        $this->setRenderType(Template::CONTENT_TYPE_RESULT);
    }

    /**
     * Устанавливает тип рендера
     *
     * @param ContentType|string $type
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setRenderType(ContentType|string $type): void
    {
        if (\is_string($type)) {
            $type = ContentType::tryFrom($type);
        }

        if ($type === null) {
            throw new \InvalidArgumentException("Unknown render type");
        }

        $this->render_type = $type;
    }

    /**
     * Ключевой метод.
     * Он всегда вызывается как `echo $template->render()`, но если вернет null - ничего не напечатает
     * После него всегда вызывается makeRedirect
     *
     * Хедеры НЕ отправляются по умолчанию: это делает только явный вызов `render(send_headers: true)`
     * (либо ручной `$template->headers->send()`).
     *
     * Для ситуации 404 мы рендерим ответ и устанавливаем хедер
     *
     * Для 404 в случае API возвращается всегда 200, но об отсутствии метода отвечает уже обработчик API
     *
     * @param bool $send_headers
     * @param bool $clean
     * @return string|null
     * @throws SmartyException
     */
    public function render(bool $send_headers = false, bool $clean = false): ?string
    {
        $content = '';
        $need_render = false;

        switch ($this->render_type) {
            case ContentType::REDIRECT: {
                $this->headers->headers_present = false;
                break;
            }
            case ContentType::JSON: {
                $content = json_encode($this->assigned_json->toArray(), TemplateInterface::JSON_ENCODE_FLAGS);
                break;
            }
            case ContentType::JSON_RAW: {
                $content = json_encode($this->assigned_json->getData(), TemplateInterface::JSON_ENCODE_FLAGS);
                break;
            }
            case ContentType::RESULT: {
                $content = json_encode($this->assigned_result, TemplateInterface::JSON_ENCODE_FLAGS);
                break;
            }
            case ContentType::RAW:
            case ContentType::RAW_HTML:
            case ContentType::JS_RAW: {
                $content = $this->assigned_raw_content;
                break;
            }
            default: {
                $need_render = true;
            }
        } // switch

        if ($this->render_type !== ContentType::REDIRECT) {
            if (($code = $this->render_type->statusCode()) !== null) {
                $this->addHeader(Headers::_, code: $code);
            } elseif (($header = $this->render_type->contentTypeHeader()) !== null) {
                $this->addHeader(Headers::CONTENT_TYPE, $header);
            }
        }

        // Судя по названию, должно отключать "именованные параметры" у хуков. Но в имеющихся тестах я не вижу никакой
        // зависимости от этого параметра. Он что включен, что выключен - поведение не меняется.
        // Смутно припоминаю, что была проблема, но судя по всему, её больше нет.
        if ($this->template_options->has('hook_disable_named_params')) {
            $this->hooks_engine->disableNamedParams($this->template_options->get('hook_disable_named_params'));
        }

        if ($need_render) {
            $content = $this->renderTemplate();
        }

        if ($this->headers->headers_present && $send_headers) {
            $this->headers->send();
        }

        if ($clean) {
            $this->clean();
        }

        return $content;
    }

    /**
     * Render Smarty Template
     *
     * @param string $default
     * @return string
     * @throws SmartyException
     * @todo: try/catch with return Result ?
     * - check is file empty
     * - check is file readable
     * - check other exceptions
     *
     */
    private function renderTemplate(string $default = ''):string
    {
        if (! ($this->smarty instanceof Smarty)) {
            $this->initSmarty();
        }

        foreach ($this->assigned_template_vars as $key => $value) {
            $this->smarty->assign($key, $value);
        }

        /*
        if (!is_readable( $full_path_to_template_file = rtrim($this->smarty->getTemplateDir(), '/') . '/' . $this->template_file)) {
            $this->logger->debug("Template file is unreadable", [ $full_path_to_template_file ]);
        }
        */

        $content
            = empty($this->template_file)
            ? $default
            : $this->smarty->fetch($this->template_file);

        // удаляем лишние переводы строк
        if ($this->template_options->get('cleanup_extra_eol')) {
            $content = preg_replace('/^\h*\v+/m', '', $content);
        }

        return $content;
    }

    /**
     * Устанавливает параметры редиректа
     *
     * @param string $uri
     * @param int $code
     * @return $this
     */
    public function setRedirect(string $uri = '/', int $code = 301):Template
    {
        $this->redirect_options = [
            '_'     =>  true,
            'uri'   =>  $uri,
            'code'  =>  $code
        ];

        $this->setRenderType( self::CONTENT_TYPE_REDIRECT );

        return $this;
    }

    /**
     * Добавляет хедер
     *
     * @param string $name
     * @param string $content
     * @param bool $replace
     * @param int $code
     * @return $this
     */
    public function addHeader(string $name = '', string $content = '', bool $replace = true, int $code = 0):Template
    {
        $this->headers->add($name, $content, $replace, $code);
        return $this;
    }

}

#-eof-#