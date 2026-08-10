<?php

namespace Arris\Presenter\System;

use Arris\Presenter\Template;

/**
 * Fluent-билдер регистрации дефолтов Template.
 *
 * Возвращается методом Template::setDefaults(); методы пишут в те же статические
 * дефолты, что и Template::addPlugin()/addClass()/addHook(), и возвращают $this
 * для цепочки. Отдельный класс нужен потому, что PHP запрещает одноимённые
 * static и instance-методы в одном классе: у Template уже есть инстансные
 * registerPlugin()/registerClass()/registerHook().
 */
final class TemplateDefaultsBuilder
{
    /**
     * Регистрирует плагин в дефолты
     *
     * @param string $type
     * @param string $name
     * @param $callback
     * @param bool $cacheable
     * @param null $cache_attr
     * @return static
     */
    public function registerPlugin(string $type, string $name, $callback, bool $cacheable = false, $cache_attr = null): static
    {
        Template::addPlugin($type, $name, $callback, $cacheable, $cache_attr);

        return $this;
    }

    /**
     * Регистрирует класс в дефолты
     *
     * @param string $name
     * @param string $implementation
     * @return static
     */
    public function registerClass(string $name, string $implementation): static
    {
        Template::addClass($name, $implementation);

        return $this;
    }

    /**
     * Регистрирует хук в дефолты
     *
     * @param string $hook_name
     * @param $hook_callback
     * @return static
     */
    public function registerHook(string $hook_name, $hook_callback): static
    {
        Template::addHook($hook_name, $hook_callback);

        return $this;
    }
}
