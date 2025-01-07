<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Repository;
use Psr\Log\LoggerInterface;

interface HooksInterface
{
    public function __construct(Repository $template_options, LoggerInterface $logger = null);

    public function disableNamedParams(bool $disable = false):Hooks;

    public function registerHook($hook_name, $hook_callback, int $priority = null):Hooks;

    public function registerHooks(array $hooks = []):Hooks;

    /**
     * @TODO
     *
     * @param callable $callback
     * @return Hooks
     */
    public function setAutoResolveMethod(callable $callback):Hooks;

    public function run(array $params, \Smarty_Internal_Template $smarty_Internal_Template):string;


}