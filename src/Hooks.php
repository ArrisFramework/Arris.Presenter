<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Helper;
use Arris\Presenter\Core\Repository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Возможно, ВСЕ определения хуков должны наследоваться от некого абстрактного класса AbstractHook ?
 * Тогда мы сможем дать хуку список обязательных параметров...
 *
 * See also:
 * https://github.com/esemve/Hook
 *
 */
class Hooks implements HooksInterface
{
    /**
     * Статичные переменные, содержащие данные о текущем обрабатываемом хуке
     */
    public static string $current_hook_name = '';

    public static array $current_hook_chain_results = [];

    public static $current_hook_callback = null;

    public static int $current_hook_priority = 0;

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
     * @todo
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
     * @param $hook_name
     * @param $hook_callback
     * @param $priority
     * @return void
     */
    public function registerHook($hook_name, $hook_callback, $priority = null):Hooks
    {
        if (is_null($priority)) {
            $this->hooks[ $hook_name ][] = $hook_callback;
        } else {
            $this->hooks[ $hook_name ][ $priority ] = $hook_callback;
        }

        return $this;
    }

    /**
     * Регистрирует массив хуков
     *
     * @param array $hooks
     * 0|name - name
     * 1|callback - callback
     * 2|priority (100) - priority
     * @return Hooks
     */
    public function registerHooks(array $hooks = []):Hooks
    {
        foreach ($hooks as $definition) {
            if (count($definition) < 2) continue;

            $name = array_key_exists('name', $definition) ? $definition['name'] : $definition[0];
            $callback = array_key_exists('callback', $definition) ? $definition['callback'] : $definition[1];
            $priority = array_key_exists('priority', $definition) ? $definition['priority'] : ($definition[2] ?? 0);

            $this->registerHook($name, $callback, $priority);
        }

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

        $hook_assign = array_key_exists('assign', $params) ? $params['assign'] : false;
        unset($params['assign']);

        if (empty($hooks = $this->getHookCallback($hook_name))) {
            if (!$this->ignore_undefined_hooks) {
                $this->logger->warning("Hook: {$hook_name} not defined");
                trigger_error("Hook: {$hook_name} not defined", E_USER_WARNING);
            }
            return '';
        }

        krsort($hooks);

        Hooks::$current_hook_name = $hook_name;

        //@todo: функциональность ТЕСТИРУЕТСЯ: $hook - массив коллбэков
        if (is_array($hooks)) {
            Hooks::$current_hook_chain_results = [];
            // $hook_execute_results = [];

            foreach ($hooks as $priority => $callback) {
                Hooks::$current_hook_callback = $callback;
                Hooks::$current_hook_priority = $priority;

                $result = $this->executeHook($callback, $params);

                if ($hook_assign) {
                    $smarty_Internal_Template->assign($hook_assign, $result);
                } else {
                    // $hook_execute_results[] = $result;
                    Hooks::$current_hook_chain_results[] = $result;
                }
            }

            // склеиваем строки результатов нескольких хуков
            $hook_execute_results = Hooks::$current_hook_chain_results;
            Hooks::$current_hook_chain_results = [];

            return implode('', $hook_execute_results);
        }

        $result = $this->executeHook($hooks, $params);

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
     * Возвращает массив исполнителей (с учетом приоритетов) хука по имени
     *
     * @param string $name
     * @return callable|null
     */
    private function getHookCallback(string $name)
    {
        $hook = array_key_exists($name, $this->hooks) ? $this->hooks[ $name ] : [];
        return $hook;
    }

}