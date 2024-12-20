<?php

namespace Arris\Presenter;

interface HeadersInterface
{
    public function add(string $header_name = '', string $header_content = 'text/html; charset=utf-8', bool $header_replace = true, int $header_code = 0):Headers;

    public function send():bool;

    public function sendHttpCode($code):void;

    public function isEmpty():bool;
}