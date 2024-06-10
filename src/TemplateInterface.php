<?php

namespace Arris\Template;

use Arris\Entity\Result;

interface TemplateInterface
{
    /**
     * Внутренние константы
     */
    const INDEX_PLUGIN_TYPE = 0;
    const INDEX_PLUGIN_NAME = 1;
    const INDEX_PLUGIN_CALLBACK = 2;
    const INDEX_PLUGIN_CACHEABLE = 3;
    const INDEX_PLUGIN_CACHEATTR = 4;

    const JSON_ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    public function __construct(array $smarty_options = [], array $template_options = [], $logger = null);

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

    public function assignResult(Result $result);

    public function setRenderType(string $type): void;

    public function addHeader(string $header_name = '', string $header_content = 'text/html; charset=utf-8', bool $header_replace = true, int $header_code = 0):Template;

    public function getAssignedTemplateVars($varName = null);

    //@todo: работа с Page title + breadcumbs (стэк)
}