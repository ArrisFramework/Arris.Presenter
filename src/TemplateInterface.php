<?php

namespace Arris\Template;

interface TemplateInterface
{
    const INDEX_PLUGIN_TYPE = 0;
    const INDEX_PLUGIN_NAME = 1;
    const INDEX_PLUGIN_CALLBACK = 2;
    const INDEX_PLUGIN_CACHEABLE = 3;
    const INDEX_PLUGIN_CACHEATTR = 4;

    const PLUGIN_FUNCTION         = 'function';
    const PLUGIN_BLOCK            = 'block';
    const PLUGIN_COMPILER         = 'compiler';
    const PLUGIN_MODIFIER         = 'modifier';
    const PLUGIN_MODIFIERCOMPILER = 'modifiercompiler';

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

    public function setTemplateDir(string $dir):Template;

    public function setCompileDir(string $dir):Template;

    public function setForceCompile(bool $force_compile):Template;

    public function registerPlugin(string $type, string $name, $callback, bool $cacheable = true, $cache_attr = null):Template;

    public function setConfigDir(string $config_dir):Template;

    public function setTemplate(string $filename = ''):Template;

    public function render(): ?string;

    public function clean($clear_cache = true):bool;

    public function setRedirect(string $uri = '/', int $code = 200):Template;

    public function isRedirect():bool;

    public function makeRedirect(string $uri = null, int $code = null, bool $replace_headers = true);

    public function assign($key, $value = null): void;

    public function assignRAW(string $html):void;

    public function assignJSON(array $json): void;

    public function setRenderType(string $type): void;

    public function addHeader(string $header_name = '', string $header_content = 'text/html; charset=utf-8', bool $header_replace = true, int $header_code = 0):Template;

    //@todo: работа с Page title + breadcumbs (стэк)
}