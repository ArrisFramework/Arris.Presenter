<?php

namespace Arris\Template;

use Smarty;
use SmartyException;
use Arris\Entity\Result;

class Template implements TemplateInterface
{
    /**
     * Константы для плагинов
     */
    const PLUGIN_FUNCTION         = 'function';
    const PLUGIN_BLOCK            = 'block';
    const PLUGIN_COMPILER         = 'compiler';
    const PLUGIN_MODIFIER         = 'modifier';
    const PLUGIN_MODIFIERCOMPILER = 'modifiercompiler';

    /**
     * Внутренние константы
     */
    const INDEX_PLUGIN_TYPE = 0;
    const INDEX_PLUGIN_NAME = 1;
    const INDEX_PLUGIN_CALLBACK = 2;
    const INDEX_PLUGIN_CACHEABLE = 3;
    const INDEX_PLUGIN_CACHEATTR = 4;

    /**
     * Типы данных
     */
    const CONTENT_TYPE_RSS  = 'rss';

    /**
     * Тип шаблонных данных JSON, вернется сериализация объекта Result
     */
    const CONTENT_TYPE_JSON = 'json';

    /**
     * Тип шаблонных данных JSON, вернется сериализация поля `data` объекта `Result`
     */
    const CONTENT_TYPE_JSON_RAW = 'json_raw';

    /**
     * Тип данных по-умолчанию
     */
    const CONTENT_TYPE_HTML = 'html';
    const CONTENT_TYPE_JS   = 'js'; // 'application/javascript'
    const CONTENT_TYPE_RAW  = 'raw';

    const CONTENT_TYPE_404  = '404';
    const CONTENT_TYPE_500  = '500';

    /**
     * Тип данных: редирект
     */
    const CONTENT_TYPE_REDIRECT = 'redirect';

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
     * Assigned-переменные шаблона
     *
     * @var array
     */
    private array $template_vars = [];

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
     * Кастомные опции Smarty для ленивой инициализации
     *
     * @var array
     */
    public array $smarty_custom_options = [];

    private array $redirect_options = [
        '_'     =>   false
    ];

    /**
     * @var string
     */
    private string $template_file = '';

    /**
     * @var string
     */
    private string $render_type = self::CONTENT_TYPE_HTML;

    /**
     * @var string
     */
    private string $raw_content = '';

    public Headers $headers;

    /**
     * @var array
     */
    public $REQUEST;

    /**
     * JSON Data
     *
     * @var Result
     */
    public $json;

    public function __construct($request = [], $smarty_options = [], $template_options = [], $logger = null)
    {
        $this->REQUEST = $request;
        $this->smarty_options = new Repository($smarty_options);
        $this->template_options = new Repository($template_options);

        //@todo: может быть template_vars тоже Repository ?

        $this->headers = new Headers();

        if (\array_key_exists('file', $template_options)) {
            $this->setTemplate($template_options['file']);
        }

        if (\array_key_exists('source', $template_options)) {
            $this->setTemplate($template_options['source']);
        }

        $this->json = new Result();
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
     * @param int $type
     * @param string $name
     * @param $callback
     * @param bool $cacheable
     * @param $cache_attr
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
            self::INDEX_PLUGIN_TYPE => $type,
            self::INDEX_PLUGIN_NAME => $name,
            self::INDEX_PLUGIN_CALLBACK => $callback,
            self::INDEX_PLUGIN_CACHEABLE => $cacheable,
            self::INDEX_PLUGIN_CACHEATTR => $cache_attr
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
     * Например, как:
     * $smarty->left_delimiter
     * $smarty->right_delimiter
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
     * Поздняя инициализация Smarty
     *
     * @return void
     * @throws SmartyException
     */
    private function initSmarty()
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

        foreach ($this->smarty_custom_options as $key => $value) {
            $this->smarty->{$key} = $value;
        }

        /**
         * Register plugins
         */
        foreach ($this->smarty_plugins as $plugin) {
            $this->smarty->registerPlugin(
                $plugin[ self::INDEX_PLUGIN_TYPE ],
                $plugin[ self::INDEX_PLUGIN_NAME],
                $plugin[ self::INDEX_PLUGIN_CALLBACK ],
                $plugin[ self::INDEX_PLUGIN_CACHEABLE ],
                $plugin[ self::INDEX_PLUGIN_CACHEATTR ]
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

    public function setTemplate(string $filename = ''): Template
    {
        $this->template_file = empty($filename) ? '' : $filename;
        return $this;
    }

    /**
     * Возвращает данные, проброшенные в шаблон
     *
     * @param $varName
     * @return array|mixed|string
     */
    public function getTemplateVars($varName = null)
    {
        return
            $varName
                ? ( \array_key_exists($varName, $this->template_vars) ? $this->template_vars[$varName] : '')
                : $this->template_vars;
    }

    public function clean($clear_cache = true): bool
    {
        $this->template_vars = [];

        if (!$clear_cache) {
            return true;
        }

        if ($this->smarty instanceof Smarty) {
            foreach ($this->smarty->getTemplateVars() as $k => $v) {
                $this->smarty->clearCache($k);
            }

            if ($clear_cache) {
                $this->smarty->clearAllCache();
            }

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
            $this->template_vars[ $key ] = $value;
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
        $this->raw_content = $html;
        $this->setRenderType( TemplateInterface::CONTENT_TYPE_RAW );
    }

    /**
     * Добавляет значения в JSON-набор данных
     *
     * @param array $json
     * @return void
     */
    public function assignJSON(array $json): void
    {
        $this->json->setData($json);
        $this->setRenderType( TemplateInterface::CONTENT_TYPE_JSON );
    }

    /**
     * Устанавливает значение JSON-набора данных напрямую (передает инстанс Result)
     *
     * @param $json
     * @return void
     */
    public function setJSON($json):void
    {
        if (is_array($json)) {
            $this->json->setData($json);
        } elseif ($json instanceof Result) {
            $this->json = $json;
        }
        $this->setRenderType( TemplateInterface::CONTENT_TYPE_JSON );
    }

    /**
     * Устанавливаает тип рендера
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
     * @param bool $send_headers
     * @param bool $clean
     * @return string|null
     * @throws SmartyException
     * @throws \JsonException
     */
    public function render(bool $send_headers = false, bool $clean = false): ?string
    {
        $content = '';
        $need_render = false;

        switch ($this->render_type) {
            case self::CONTENT_TYPE_REDIRECT: {
                $this->headers->need_send_headers = false;
                break;
            }
            case self::CONTENT_TYPE_JSON: {
                $content = \json_encode($this->json->getAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                $this->addHeader(Headers::CONTENT_TYPE,'application/json; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_JSON_RAW: {
                $content = \json_encode($this->json->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                $this->addHeader(Headers::CONTENT_TYPE,'application/json; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_RAW: {
                $content = $this->raw_content;
                $this->addHeader(Headers::CONTENT_TYPE, 'text/html; charset=utf-8');
                break;
            }
            case self::CONTENT_TYPE_JS: {
                $content = $this->raw_content;
                $this->addHeader(Headers::CONTENT_TYPE, 'text/javascript;charset=utf-8');
                break;
            }
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

        if ($need_render) {
            $content = $this->renderTemplate();
        }

        if ($send_headers) {
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
     * @return string
     * @throws SmartyException
     */
    private function renderTemplate():string
    {
        if (! ($this->smarty instanceof Smarty)) {
            $this->initSmarty();
        }

        foreach ($this->template_vars as $key => $value) {
            $this->smarty->assign($key, $value);
        }

        $content
            = empty($this->template_file)
            ? ''
            : $this->smarty->fetch($this->template_file);

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
     * @param string $header_name
     * @param string $header_content
     * @param bool $header_replace
     * @param int $header_code
     * @return $this
     */
    public function addHeader(string $header_name = '', string $header_content = 'text/html; charset=utf-8', bool $header_replace = true, int $header_code = 0):Template
    {
        $this->headers->add($header_name, $header_content, $header_replace, $header_code);
        return $this;
    }

}