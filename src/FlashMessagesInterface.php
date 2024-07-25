<?php

namespace Arris\Template;

interface FlashMessagesInterface
{
    public function __construct(&$storage = null, $storageKey = null);

    public static function getInstance():FlashMessages;

    public function addMessage($key, $message): void;

    public function addMessageNow($key, $message): void;

    public function getMessages(): array;

    public function getMessage($key);

    public function getFirstMessage(string $key, string $default = null);

    public function hasMessage(string $key): bool;

    public function clearMessages(): void;

    public function clearMessage(string $key): void;

}