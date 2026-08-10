<?php

namespace Arris\Presenter\System;

/**
 * Тип контента презентера.
 *
 * Backed-string enum: значения совпадают со старыми строковыми константами
 * Template::CONTENT_TYPE_*, которые сохранены как алиасы на кейсы enum
 * (Template::CONTENT_TYPE_HTML === ContentType::HTML) для обратной совместимости.
 *
 * Enum — единый источник истины для хедеров контента и HTTP-статусов,
 * заменяет удалённую мёртвую карту Template::CONTENT_HEADER_TYPES.
 */
enum ContentType: string
{
    case RSS            = 'rss';
    case XML            = 'xml';

    case JSON           = 'json';
    case JSON_RAW       = 'json_raw';

    case HTML           = 'html';

    case JS             = 'js';
    case JS_RAW         = 'js-raw';

    case RAW            = 'raw';
    case RAW_HTML       = 'raw_html';

    case RESULT         = 'result';
    case REDIRECT       = 'redirect';

    case NOT_FOUND      = '404';
    case INTERNAL_ERROR = '500';

    /**
     * Значение хедера Content-Type (без имени 'Content-Type').
     * null — тип не требует хедера контента (RAW, REDIRECT, 404/500).
     *
     * @return string|null
     */
    public function contentTypeHeader(): ?string
    {
        return match ($this) {
            self::RSS, self::XML                         => 'application/xml; charset=utf-8',
            self::JSON, self::JSON_RAW, self::RESULT     => 'application/json; charset=utf-8',
            self::HTML, self::RAW_HTML                   => 'text/html; charset=utf-8',
            self::JS, self::JS_RAW                       => 'text/javascript; charset=utf-8',
            self::RAW, self::REDIRECT, self::NOT_FOUND, self::INTERNAL_ERROR => null,
        };
    }

    /**
     * HTTP-статус для типов-ошибок
     *
     * @return int|null
     */
    public function statusCode(): ?int
    {
        return match ($this) {
            self::NOT_FOUND      => 404,
            self::INTERNAL_ERROR => 500,
            default              => null,
        };
    }

    /**
     * Нужен ли шаблонный (smarty) рендер для этого типа
     *
     * @return bool
     */
    public function needsTemplateRender(): bool
    {
        return match ($this) {
            self::HTML, self::JS, self::XML, self::RSS, self::NOT_FOUND, self::INTERNAL_ERROR => true,
            default => false,
        };
    }
}
