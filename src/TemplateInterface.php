<?php

namespace Arris\Template;

interface TemplateInterface
{
    const INDEX_PLUGIN_TYPE = 0;
    const INDEX_PLUGIN_NAME = 1;
    const INDEX_PLUGIN_CALLBACK = 2;
    const INDEX_PLUGIN_CACHEABLE = 3;
    const INDEX_PLUGIN_CACHEATTR = 4;


    public const HTTP_CODES = array(
        100 => "HTTP/1.1 100 Continue",
        101 => "HTTP/1.1 101 Switching Protocols",
        200 => "HTTP/1.1 200 OK",
        201 => "HTTP/1.1 201 Created",
        202 => "HTTP/1.1 202 Accepted",
        203 => "HTTP/1.1 203 Non-Authoritative Information",
        204 => "HTTP/1.1 204 No Content",
        205 => "HTTP/1.1 205 Reset Content",
        206 => "HTTP/1.1 206 Partial Content",
        300 => "HTTP/1.1 300 Multiple Choices",
        301 => "HTTP/1.1 301 Moved Permanently",
        302 => "HTTP/1.1 302 Found",
        303 => "HTTP/1.1 303 See Other",
        304 => "HTTP/1.1 304 Not Modified",
        305 => "HTTP/1.1 305 Use Proxy",
        307 => "HTTP/1.1 307 Temporary Redirect",
        400 => "HTTP/1.1 400 Bad Request",
        401 => "HTTP/1.1 401 Unauthorized",
        402 => "HTTP/1.1 402 Payment Required",
        403 => "HTTP/1.1 403 Forbidden",
        404 => "HTTP/1.1 404 Not Found",
        405 => "HTTP/1.1 405 Method Not Allowed",
        406 => "HTTP/1.1 406 Not Acceptable",
        407 => "HTTP/1.1 407 Proxy Authentication Required",
        408 => "HTTP/1.1 408 Request Time-out",
        409 => "HTTP/1.1 409 Conflict",
        410 => "HTTP/1.1 410 Gone",
        411 => "HTTP/1.1 411 Length Required",
        412 => "HTTP/1.1 412 Precondition Failed",
        413 => "HTTP/1.1 413 Request Entity Too Large",
        414 => "HTTP/1.1 414 Request-URI Too Large",
        415 => "HTTP/1.1 415 Unsupported Media Type",
        416 => "HTTP/1.1 416 Requested range not satisfiable",
        417 => "HTTP/1.1 417 Expectation Failed",
        500 => "HTTP/1.1 500 Internal Server Error",
        501 => "HTTP/1.1 501 Not Implemented",
        502 => "HTTP/1.1 502 Bad Gateway",
        503 => "HTTP/1.1 503 Service Unavailable",
        504 => "HTTP/1.1 504 Gateway Time-out"
    );

    const CONTENT_TYPE_RSS  = 'rss';
    const CONTENT_TYPE_JSON = 'json';
    const CONTENT_TYPE_404  = '404';
    const CONTENT_TYPE_HTML = 'html';
    const CONTENT_TYPE_JS   = 'js'; // 'application/javascript'
    const CONTENT_TYPE_RAW  = 'raw';

    const CONTENT_TYPE_REDIRECT = 'redirect';

    /**
     * Отсылаемые заголовки для разных типов контента
     */
    const HEADERS = [
        '_'                         =>  [
            // это позволит нам посылать несколько заголовков другого типа через прямой вызов
            // 'Content-Type: text/html; charset=utf-8'
        ],
        self::CONTENT_TYPE_HTML     =>  [
            [ Headers::CONTENT_TYPE , 'text/html; charset=utf-8']
        ],
        self::CONTENT_TYPE_JS       =>  [
            [ Headers::CONTENT_TYPE , 'text/javascript;charset=utf-8']
        ],
        self::CONTENT_TYPE_JSON     =>  [
            'Content-Type: application/json; charset=utf-8'
        ],
        self::CONTENT_TYPE_RSS      =>  [
            'Content-type: application/xml'
        ],
        self::CONTENT_TYPE_404      =>  [
            "HTTP/1.0 404 Not Found"
        ],
        self::CONTENT_TYPE_RAW      =>  [
            "Content-Type: text/html; charset=utf-8"
        ],
        self::CONTENT_TYPE_REDIRECT =>  [
            // nothing
        ]
    ];

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