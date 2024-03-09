<?php

namespace Arris\Template;

use Smarty;
use SmartyException;

class Template implements TemplateInterface
{
    /**
     * Smarty instance
     *
     * @var Smarty
     */
    private ?Smarty $smarty = null;

    /**
     * Smarty Options for deferred init
     *
     * @var Config
     */
    private $smarty_options;

    /**
     * @var Config
     */
    private $template_options;

    /**
     * Assigned-переменные шаблона
     *
     * @var array
     */
    private $template_vars = [];

    /**
     * Smarty Plugins for deferred init
     *
     * @var array
     */
    private array $smarty_plugins = [];

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

    public function __construct($request = [], $smarty_options = [], $template_options = [], $logger = null)
    {
        $this->REQUEST = $request;
        $this->smarty_options = new Config($smarty_options);
        $this->template_options = new Config($template_options);
        $this->headers = new Headers();

        if (\array_key_exists('file', $template_options)) {
            $this->setTemplate($template_options['file']);
        }

        if (\array_key_exists('source', $template_options)) {
            $this->setTemplate($template_options['source']);
        }
    }

    public function setTemplateDir(string $dir):Template
    {
        $this->smarty_options->setTemplateDir = $dir;
        return $this;
    }

    public function setCompileDir(string $dir):Template
    {
        $this->smarty_options->setCompileDir = $dir;
        return $this;
    }

    public function setForceCompile(bool $force_compile):Template
    {
        $this->smarty_options->set('setForceCompile', $force_compile);

        return $this;
    }

    /**
     * @throws SmartyException
     */
    public function registerPlugin(int $type, string $name, $callback, $cacheable = true, $cache_attr = null):Template
    {
        if (!is_callable($callback)) {
            throw new SmartyException("Plugin '{$name}' not callable");
        }

        if ($cacheable && $cache_attr) {
            throw new SmartyException("Cannot set caching attributes for plugin '{$name}' when it is cacheable.");
        }

        $this->smarty_plugins[] = [
            self::INDEX_PLUGIN_TYPE => $type,
            self::INDEX_PLUGIN_NAME => $name,
            self::INDEX_PLUGIN_CALLBACK => $callback,
            self::INDEX_PLUGIN_CACHEABLE => $cacheable,
            self::INDEX_PLUGIN_CACHEATTR => $cache_attr
        ];
        return $this;
    }

    public function setConfigDir(string $config_dir):Template
    {
        $this->smarty_options->set('setConfigDir', $config_dir);
        return $this;
    }

    /**
     * Поздняя инициализация Smarty
     *
     * @return void
     * @throws SmartyException
     */
    public function initSmarty()
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

    }

    public function setRedirectOptions(string $uri = '/', int $code = 200):self
    {
        $this->redirect_options = [
            '_'     =>  true,
            'uri'   =>  $uri,
            'code'  =>  $code
        ];

        return $this;
    }

    /**
     * @return bool
     */
    public function isRedirect():bool
    {
        return $this->redirect_options['_'];
    }

    /**
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
            = is_null($uri)
            ? (
                array_key_exists('uri', $this->redirect_options)
                    ? $this->redirect_options['uri']
                    : null
            )
            : $uri;

        $_code
            = is_null($code)
            ? (
                array_key_exists('code', $this->redirect_options)
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

        if (
            strpos( $_uri, "http://" ) !== false ||
            strpos( $_uri, "https://" ) !== false
        ) {
            $location = $_uri;
        } else {
            $scheme = Helper::is_ssl() ? "https://" : "http://";
            $scheme = str_replace('://', '', $scheme);
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
                ? ( array_key_exists($varName, $this->template_vars) ? $this->template_vars[$varName] : '')
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
     * Assing Template variables
     *
     * @param $key
     * @param $value
     * @return void
     */
    public function assign($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->assign($k, $v);
            }
        } else {
            $this->template_vars[ $key ] = $value;
        }
    }

    public function assignRAW(string $html):void
    {
        $this->raw_content = $html;
        $this->setRenderType( TemplateInterface::CONTENT_TYPE_RAW );
    }

    public function assignJSON(array $json): void
    {
        foreach ($json as $key => $value) {
            $this->assign($key, $value);
        }
        $this->setRenderType( TemplateInterface::CONTENT_TYPE_JSON );
    }

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
     * @param bool $send_header
     * @param bool $clean
     * @return string|null
     * @throws SmartyException
     * @throws \JsonException
     */
    public function render(bool $send_header = true, bool $clean = false): ?string
    {
        $content = '';
        $need_render = false;

        switch ($this->render_type) {
            case self::CONTENT_TYPE_REDIRECT: {
                $this->headers->send = false;
                break;
            }
            case self::CONTENT_TYPE_JSON: {
                $content = \json_encode($this->template_vars, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
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

        $this->headers->send();

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

    public function setRedirect(string $uri = '/', int $code = 200): void
    {
        $this->redirect_options = [
            '_'     =>  true,
            'uri'   =>  $uri,
            'code'  =>  $code
        ];

        $this->setRenderType( self::CONTENT_TYPE_REDIRECT );
    }

    /**
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

    public function setHttpResponse($code):Template
    {
        $this->headers->add(Headers::_, Headers::HTTP_CODES[$code]);
        return $this;
    }
}