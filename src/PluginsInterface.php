<?php

namespace Arris\Template;

use Smarty;

interface PluginsInterface
{
    public static function register(Smarty $smarty, $plugins = []): void;

    /**
     * PLUGINS
     */

    public static function size_format(int $size, array $params):string;

    public static function dd(): void;

    public static function _env($params):string;

    public static function pluralForm($number, $forms):string;

}