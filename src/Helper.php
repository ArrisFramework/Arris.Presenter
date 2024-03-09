<?php

namespace Arris\Template;

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
            if ('on' == strtolower($_SERVER['HTTPS'])) {
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
    public static function getAllowedValue( $data, $allowed_values_array, $default_value = NULL)
    {
        if (empty($data)) {
            return $default_value;
        } else {
            $key = array_search($data, $allowed_values_array);
            return ($key !== FALSE )
                ? $allowed_values_array[ $key ]
                : $default_value;
        }
    }



}