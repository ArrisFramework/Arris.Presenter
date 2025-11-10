<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Helper;
use Arris\Presenter\Core\Repository;
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
     * Типы плагинов для Smarty
     */
    const PLUGIN_FUNCTION         = 'function';
    const PLUGIN_BLOCK            = 'block';
    const PLUGIN_COMPILER         = 'compiler';
    const PLUGIN_MODIFIER         = 'modifier';
    const PLUGIN_MODIFIERCOMPILER = 'modifiercompiler';

    /**
     * Типы данных для рендера
     */
    const CONTENT_TYPE_RSS  = 'rss'; // RSS
    const CONTENT_TYPE_XML  = 'xml';

    const CONTENT_TYPE_JSON = 'json'; // Тип шаблонных данных JSON RAW
    const CONTENT_TYPE_JSON_RAW = 'json_raw'; // Тип шаблонных данных JSON RAW

    const CONTENT_TYPE_HTML = 'html';

    const CONTENT_TYPE_JS   = 'js'; // 'application/javascript'
    const CONTENT_TYPE_JS_RAW = 'js-raw'; // 'application/javascript'

    const CONTENT_TYPE_RAW  = 'raw';
    const CONTENT_TYPE_RAW_HTML = 'raw_html';

    const CONTENT_TYPE_RESULT = 'result';

    const CONTENT_TYPE_REDIRECT = 'redirect';

    const CONTENT_TYPE_404  = '404';
    const CONTENT_TYPE_500  = '500';

    /**
     * Available content header types
     */
    const CONTENT_HEADER_TYPES = [
        self::CONTENT_TYPE_RSS      =>  'Content-type: application/xml',
        self::CONTENT_TYPE_XML      =>  'Content-type: application/xml',

        self::CONTENT_TYPE_JSON     =>  'Content-Type: application/json; charset=utf-8',
        self::CONTENT_TYPE_JSON_RAW =>  'Content-Type: application/json; charset=utf-8',

        self::CONTENT_TYPE_HTML     =>  "Content-Type: text/html; charset=utf-8",

        self::CONTENT_TYPE_JS       =>  "Content-Type: text/javascript;charset=utf-8",
        self::CONTENT_TYPE_JS_RAW   =>  "Content-Type: text/javascript;charset=utf-8",

        self::CONTENT_TYPE_RAW      =>  "Content-Type: text/html; charset=utf-8",
        self::CONTENT_TYPE_RAW_HTML =>  "",

        self::CONTENT_TYPE_RESULT   =>  'Content-Type: application/json; charset=utf-8',

        self::CONTENT_TYPE_404      =>  "HTTP/1.0 404 Not Found",
        self::CONTENT_TYPE_500      =>  "HTTP/1.0 500 Internal server error",

        '_'                         =>  "Content-Type: text/html; charset=utf-8",
    ];

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
    private $template_options;

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

    // public bool $smarty_custom_option_escape_html = false;

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
     * @var string
     */
    private string $render_type = self::CONTENT_TYPE_HTML;

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
     * Конструктор презентера
     *
     * @param array $smarty_options
     * @param array $template_options
     * @param $logger
     */
    public function __construct(array $template_options = [], array $smarty_options = [], $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();

        $this->smarty_options = new Repository($smarty_options);

        $this->template_options = new Repository($template_options);

        $this->headers = new Headers($this->logger);

        $this->breadcrumbs = new Breadcrumbs($this->logger);

        $this->hooks_engine = new Hooks($this->template_options, $this->logger);

        if (\array_key_exists('file', $template_options)) {
            $this->setTemplate($template_options['file']);
        }

        if (\array_key_exists('source', $template_options)) {
            $this->setTemplate($template_options['source']);
        }

        // удалять ли лишние переводы строк при рендере?
        $this->setEngineOption(
            'cleanup_extra_eol',
            $template_options['cleanup_extra_eol'] ?? true
        );

        //@todo: может быть template_vars тоже Repository ?

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
        $this->smarty_options->setTemplateDir = $dir;
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
        $this->smarty_options->setCompileDir = $dir;
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
        $this->smarty_options->setForceCompile = $force_compile;
        $this->smarty_options->set('setForceCompile', $force_compile);

        return $this;
    }

    /**
     * @param string $config_dir
     * @return $this
     */
    public function setConfigDir(string $config_dir):Template
    {
        $this->smarty_options->setConfigDir = $config_dir;
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
        if (is_array($key_value)) {
            foreach ($key_name as $ki => $kv) {
                $this->setEngineOption($ki, $kv);
            }
        } else {
            $this->template_options[$key_name] = $key_value;
        }

        return $this;
    }

    public function registerHook($hook, $hook_callback = null, int $priority = null):Template
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

        if ( \preg_match('/^http[s]?:\/\//', $_uri) !== false ) {
            $location = $_uri;
        } else {
            $scheme = Helper::is_ssl() ? "https://" : "http://";
            $scheme = \str_replace('://', '', $scheme);
            $location = "{$scheme}://{$_SERVER['HTTP_HOST']}{$_uri}";
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
        $this->assigned_json = null;
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
     * @param string $type
     * @return void
     */
    public function setRenderType(string $type): void
    {
        $this->render_type = $type;
    }

    /**
     * Ключевой метод.
     * Он всегда вызывается как `echo $template->render()`, но если вернет null - ничего не напечатает
     * После него всегда вызывается makeRedirect
     *
     * Хедеры мы отправляем всегда кроме случая редиректа
     *
     * Для ситуации 404 мы рендерим ответ и устанавливаем хедер
     *
     * Для 404 в случае API возвращается всегда 200, но об отсутствии метода отвечает уже обработчик API
     *
     * @param bool $need_send_headers
     * @param bool $clean
     * @return string|null
     * @throws SmartyException
     */
    public function render(bool $need_send_headers = true, bool $clean = false): ?string
    {
        $content = '';
        $need_render = false;

        switch ($this->render_type) {
            case self::CONTENT_TYPE_REDIRECT: {
                $this->headers->headers_present = false;
                break;
            }
            case self::CONTENT_TYPE_JSON: {
                $content = json_encode($this->assigned_json->toArray(), TemplateInterface::JSON_ENCODE_FLAGS);
                $this->addHeader(Headers::CONTENT_TYPE,'application/json; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_JSON_RAW: {
                $content = json_encode($this->assigned_json->getData(), TemplateInterface::JSON_ENCODE_FLAGS);
                $this->addHeader(Headers::CONTENT_TYPE,'application/json; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_RESULT: {
                $content = json_encode($this->assigned_result, TemplateInterface::JSON_ENCODE_FLAGS);
                $this->addHeader(Headers::CONTENT_TYPE,'application/json; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_RAW: {
                $content = $this->assigned_raw_content;
                break;
            }
            case self::CONTENT_TYPE_RAW_HTML: {
                $content = $this->assigned_raw_content;
                $this->addHeader(Headers::CONTENT_TYPE, 'text/html; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_JS: {
                $need_render = true;
                $this->addHeader(Headers::CONTENT_TYPE, 'text/javascript;charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_JS_RAW: {
                $content = $this->assigned_raw_content;
                $this->addHeader(Headers::CONTENT_TYPE, 'text/javascript;charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_XML:
            case self::CONTENT_TYPE_RSS: {
                $this->addHeader(Headers::CONTENT_TYPE, 'Content-type: application/xml');
                $need_render = true;
                break;
            }
            case self::CONTENT_TYPE_HTML: {
                $this->addHeader(Headers::CONTENT_TYPE, 'text/html; charset=utf-8');
                $need_render = true;
                break;
            }
            case self::CONTENT_TYPE_404: {
                $this->addHeader(Headers::_, Headers::HTTP_CODES[404]);
                $need_render = true;
                break;
            }
            case self::CONTENT_TYPE_500: {
                $this->addHeader(Headers::_, Headers::HTTP_CODES[500]);
                $need_render = true;
                break;
            }
            default: {

            }
        } // switch

        // Судя по названию, должно отключать "именованные параметры" у хуков. Но в имеющихся тестах я не вижу никакой
        // зависимости от этого параметра. Он что включен, что выключен - поведение не меняется.
        // Смутно припоминаю, что была проблема, но судя по всему, её больше нет.
        if ($this->template_options->has('hook_disable_named_params')) {
            $this->hooks_engine->disableNamedParams($this->template_options->get('hook_disable_named_params'));
        }

        if ($need_render) {
            $content = $this->renderTemplate();
        }

        if ($this->headers->headers_present && $need_send_headers) {
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
    public function setRedirect(string $uri = '/', int $code = 200):Template
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