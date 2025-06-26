<?php

namespace Arris\Presenter;

use Psr\Log\LoggerInterface;

interface HeadersInterface
{
    public function __construct(LoggerInterface $logger = null);

    public function add(string $name = '', string $content = 'text/html; charset=utf-8', bool $replace = true, int $code = 0):Headers;

    public function has(string $name): bool;

    public function send():bool;

    public function sendHttpCode($code):void;

    public function isEmpty():bool;

    public function clean();
}