<?php

namespace Arris\Template;

interface TemplateInterface
{
    const INDEX_PLUGIN_TYPE = 0;
    const INDEX_PLUGIN_NAME = 1;
    const INDEX_PLUGIN_CALLBACK = 2;
    const INDEX_PLUGIN_CACHEABLE = 3;
    const INDEX_PLUGIN_CACHEATTR = 4;

    const CONTENT_TYPE_RSS  = 'rss';
    const CONTENT_TYPE_JSON = 'json';
    const CONTENT_TYPE_HTML = 'html';
    const CONTENT_TYPE_JS   = 'js'; // 'application/javascript'
    const CONTENT_TYPE_RAW  = 'raw';

    const CONTENT_TYPE_404  = '404';
    const CONTENT_TYPE_500  = '500';

    const CONTENT_TYPE_REDIRECT = 'redirect';

    /**
     * @param $request
     * @param $smarty_options
     * @param $template_options
     * @param $logger
     */
    public function __construct($request = [], $smarty_options = [], $template_options = [], $logger = null);

    /**
     * @param string $dir
     * @return $this
     */
    public function setTemplateDir(string $dir):Template;

    /**
     * @param string $dir
     * @return $this
     */
    public function setCompileDir(string $dir):Template;

    /**
     * @param bool $force_compile
     * @return $this
     */
    public function setForceCompile(bool $force_compile):Template;

    /**
     * Регистрирует плагин для поздней инициализации
     *
     * @param int $type
     * @param string $name
     * @param $callback
     * @param $cacheable
     * @param $cache_attr
     * @return $this
     */
    public function registerPlugin(int $type, string $name, $callback, $cacheable = true, $cache_attr = null):Template;

    /**
     * @param string $config_dir
     * @return $this
     */
    public function setConfigDir(string $config_dir):Template;

    public function setTemplate(string $filename = ''):Template;

    public function render(): ?string;

    public function clean($clear_cache = true):bool;

}