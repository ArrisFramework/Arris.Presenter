<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Repository;
use Psr\Log\LoggerInterface;

interface HooksInterface
{
    public function __construct(Repository $template_options, LoggerInterface $logger = null);

    public function disableNamedParams(bool $disable = false):Hooks;

    public function registerHook($hook, $hook_callback, int $priority = 0):Hooks;

    public function setAutoResolveMethod(callable $callback):Hooks;

    public function run(array $params, \Smarty_Internal_Template $smarty_Internal_Template):string;
}