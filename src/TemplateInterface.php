<?php

namespace Arris\Template;

interface TemplateInterface
{


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

    public function registerClass(string $name, string $implementation):Template;

    public function setSmartyNativeOption($key_name, $key_value):Template;

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

    public function setJSON($json):void;

    public function setRenderType(string $type): void;

    public function addHeader(string $header_name = '', string $header_content = 'text/html; charset=utf-8', bool $header_replace = true, int $header_code = 0):Template;

    //@todo: работа с Page title + breadcumbs (стэк)
}