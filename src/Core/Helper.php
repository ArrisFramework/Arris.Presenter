<?php

namespace Arris\Presenter\Core;

class Helper
{
    /**
     * Подключено по HTTP или HTTPS?
     *
     * @return bool
     */
    public static function is_ssl():bool
    {
        if (isset($_SERVER['HTTPS'])) {
            if ('on' == \strtolower($_SERVER['HTTPS'])) {
                return true;
            }
            if ('1' == $_SERVER['HTTPS']) {
                return true;
            }
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        }
        return false;
    }

    /**
     * Проверяет заданную переменную на допустимость (на основе массива допустимых значений)
     * и если находит - возвращает её. В противном случае возвращает $default_value (по умолчанию NULL).
     *
     * @param $data
     * @param $allowed_values_array
     * @param $default_value
     * @return null|mixed
     */
    public static function getAllowedValue($data, $allowed_values_array, $default_value = null)
    {
        if (empty($data)) {
            return $default_value;
        } else {
            $key = \array_search($data, $allowed_values_array);
            return ($key !== false )
                ? $allowed_values_array[ $key ]
                : $default_value;
        }
    }

    public static function compileHandler($handler)
    {
        if (empty($handler)) {
            return [];
        }

        if ($handler instanceof \Closure) {
            $actor = $handler;
        } elseif (is_array($handler) || (is_string($handler) && strpos($handler, '@') > 0)) {
            // [ \Path\To\Class:class, "method" ] or 'Class@method'

            if (is_string($handler)) {
                list($class, $method) = self::explode($handler, [null, '__invoke'], '@');
            } else {
                list($class, $method) = $handler;
            }

            $i_class = new $class();

            $actor = [ $i_class, $method ];

        } elseif (strpos($handler, '::')) {
            // static method
            list($class, $method) = self::explode($handler, [null, ''], '::');

            $actor = [ $class, $method ];

        }  else {
            // function
            $actor = $handler;
        }

        return $actor;
    }

    /**
     * Выполняет explode строки роута с учетом дефолтной маски
     * Заменяет list($a, $b) = explode(separator, string) с дефолтными значениями элементов
     * Хотел назвать это replace_array_callback(), но передумал
     *
     * @param $income
     * @param array $default
     * @param string $separator
     * @return array
     */
    private static function explode($income, array $default = [ null, '__invoke' ], string $separator = '@'): array
    {
        return array_map(static function($first, $second) {
            return empty($second) ? $first : $second;
        }, $default, \explode($separator, $income));
    }



}