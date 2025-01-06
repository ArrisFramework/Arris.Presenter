<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Helper;
use Arris\Presenter\Core\Repository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @todo: возможно, ВСЕ определения хуков должны наследоваться от некого абстрактного класса AbstractHook ?
 * Тогда мы сможем дать хуку список обязательных параметров...
 *
 * See also: https://github.com/esemve/Hook
 *
 */
class Hooks implements HooksInterface
{
    /**
     * Логгер
     * @var LoggerInterface|NullLogger
     */
    private $logger;

    /**
     * Массив хуков
     *
     * @var array
     */
    public array $hooks = [];

    /**
     * Отключить ли парсинг именованных параметров в хуках?
     * По умолчанию: FALSE
     *
     * @var bool
     */
    public bool $disable_named_params = false;

    /**
     * Игнорировать неопределенные хуки
     *
     * @var bool TRUE
     */
    public bool $ignore_undefined_hooks = true;

    /**
     * Метод для авторезолва ненайденных методов хуков
     *
     * @var callable
     */
    public $auto_resolve_method = null;

    /**
     * @param Repository $template_options
     * - hook_disable_named_params FALSE
     * - ignore_undefined_hooks TRUE - если метод хука не найден/не определен - возвращаем пустую строку как результат хука
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(Repository $template_options, LoggerInterface $logger = null)
    {
        $this->disable_named_params = (bool)($template_options['hook_disable_named_params'] ?? false);
        $this->ignore_undefined_hooks = (bool)($template_options['ignore_undefined_hooks'] ?? true);

        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Поздняя установка опции hook_disable_named_params
     * @param bool $disable
     * @return $this
     */
    public function disableNamedParams(bool $disable = false):Hooks
    {
        $this->disable_named_params = $disable;
        return $this;
    }

    /**
     * Элементарный метод регистрации хука. Не проверяет наличие подобного хука, не проверяет его существование, не реализует механизм приоритетов
     *
     * Хуки должны вызываться по списку приоритетов, от максимума к минимуму
     *
     * @param $hook
     * @param $hook_callback
     * @param int $priority
     * @return void
     */
    public function registerHook($hook, $hook_callback, int $priority = 0):Hooks
    {
        $this->hooks[ $hook ] = $hook_callback;

        return $this;
    }

    /**
     * @todo: механизм, реализующий авторезолв обработчиков хуков
     *
     * @param callable $callback
     * @return $this
     */
    public function setAutoResolveMethod(callable $callback):Hooks
    {
        $this->auto_resolve_method = $callback;

        return $this;
    }

    /**
     * Запуск хука
     *
     * @param array $params
     * @param \Smarty_Internal_Template $smarty_Internal_Template
     * @return string
     */
    public function run(array $params, \Smarty_Internal_Template $smarty_Internal_Template):string
    {
        if (empty($params['run'])) {
            $this->logger->warning("Hook: missing 'run' parameter");
            trigger_error("Hook: missing 'run' parameter", E_USER_WARNING);
            return '';
        }

        $hook_name = $params['run'];
        unset($params['run']);

        if (is_null($hook = $this->getHookCallback($hook_name))) {
            if (!$this->ignore_undefined_hooks) {
                $this->logger->warning("Hook: {$hook_name} not defined");
                trigger_error("Hook: {$hook_name} not defined", E_USER_WARNING);
            }
            return '';
        }

        $hook_assign = array_key_exists('assign', $params) ? $params['assign'] : false;
        unset($params['assign']);

        //@todo: функциональность заложена, но ПОКА ЧТО $hook всегда callable, а не массив!
        /*if (is_array($hook)) {
            $hook_execute_results = [];

            foreach ($hook as $callback) {
                $result = $this->executeHook($callback, $params);

                if ($hook_assign) {
                    $smarty_Internal_Template->assign($hook_assign, $result);
                } else {
                    $hook_execute_results[] = $result;
                }
            }

            // склеиваем строки результатов нескольких хуков
            return implode('', $hook_execute_results);
        }*/

        $result = $this->executeHook($hook, $params);

        if ($hook_assign) {
            $smarty_Internal_Template->assign($hook_assign, $result);
        } else {
            return $result;
        }

        return '';
    }

    /**
     * Internal call
     *
     * @param $hook
     * @param $params
     * @return mixed
     */
    private function executeHook($hook, $params)
    {
        $args = $this->disable_named_params ? array_values($params) : $params;

        // тут нужно делать инстанциирование обработчиков (аналогично compileCallbackHandler?)
        // и прочую логику запуска хуков, возможно, в зависимости от их типа

        $actor = Helper::compileHandler($hook);

        return call_user_func_array($actor, $args);
    }

    /**
     * Возвращает метод-исполнитель хука по имени
     *
     * @todo: на самом деле должен возвращать массив исполнителей (с учетом приоритетов)
     *
     * @param string $name
     * @return callable|null
     */
    private function getHookCallback(string $name)
    {
        $hook = array_key_exists($name, $this->hooks) ? $this->hooks[ $name ] : null;
        return $hook;
    }

}