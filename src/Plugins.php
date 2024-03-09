<?php

namespace Arris\Template;

use ReflectionClass;
use ReflectionException;
use Smarty;
use SmartyException;

class Plugins
{
    public static $already_registred = [];

    /**
     * Регистрирует метод класса TemplatePlugins как плагин.
     *
     * В аннотации метода используются следующие параметры:
     *
     * - `@smarty_plugin_type` - обязательно, тип плагина: function|block|compiler|modifier
     * - `@smarty_plugin_name` - опционально, имя, под которым регистрируем метод. По умолчанию совпадает с именем функции.
     * - `@smarty_plugin_cacheable` - опционально, тип кэширования, true|false (default false)
     *
     * NB: Дополнение: сейчас можно регистрировать только статический метод из класса TemplatePlugins
     *
     * TODO: Хорошо бы дополнить этот механизм возможность регистрировать плагином произвольную функцию произвольного класса:
     * \AJUR\TemplatePlugins::register($SMARTY, [ "size_format", "MyClass::convert", "_func" => "MyClass::func" ]);
     *
     * Как мы можем передавать функцию:
     * 1) строка - ищется сначала в TemplatePlugins::method, потом method в глобальной области видимости среди функций
     * 2) массив [class, method]
     * 3) хорошо бы расширенный строковый формат "Class::method" -> [Class, method]
     *
     * @throws ReflectionException
     * @throws SmartyException
     */
    public static function register(Smarty $smarty, $plugins = []): void
    {
        foreach ($plugins as $entity) {
            if (in_array($entity, self::$already_registred)) {
                continue;
            }

            if (is_callable([self::class, $entity])) {
                /**
                 * Для определения параметров подключаемого плагина мы парсим аннотации:
                 * https://www.php.net/manual/en/reflectionclass.getdoccomment.php
                 */
                $comment_string = (new ReflectionClass(self::class))->getMethod($entity)->getDocComment();

                // $pattern = "#(@[a-zA-Z]+\s*[a-zA-Z0-9, ()_].*)#";
                // preg_match_all($pattern, $comment_string, $matches, PREG_PATTERN_ORDER);

                $pattern_smarty_plugin_type = "#@smarty_plugin_type\s([a-zA-Z]+)#";
                preg_match_all($pattern_smarty_plugin_type, $comment_string, $matches_type, PREG_PATTERN_ORDER);

                $modifier_type = empty($matches_type[1]) ? 'null' : $matches_type[1][0];
                $modifier_type = Helper::getAllowedValue($modifier_type, [
                    'function', 'block', 'compiler', 'modifier'
                ], null);

                $pattern_smarty_plugin_type = "#@smarty_plugin_cacheable\s([a-zA-Z]+)#";
                preg_match_all($pattern_smarty_plugin_type, $comment_string, $matches_cacheable, PREG_PATTERN_ORDER);
                $matches_cacheable = empty($matches_cacheable[1]) ? 'true' : $matches_cacheable[1][0];

                $modifier_cacheable = Helper::getAllowedValue($matches_cacheable, [
                    'true', 'false'
                ], 'false');
                $modifier_cacheable = !(($modifier_cacheable === 'false'));

                // теперь регистрируем хэндлер:
                //
                // Как мы можем передавать функцию?
                // строка - ищется сначала в TemplatePlugins::method, потом method в глобальной области видимости среди функций
                // массив [class, method]
                // хорошо бы расширенный строковый формат "Class::method" -> [Class, method]
                $smarty->registerPlugin($modifier_type, $entity, [self::class, $entity], $modifier_cacheable);

                self::$already_registred[] = $entity;
            }
        }
    }

    /**
     * Форматирует вывод размера файла
     *
     * @smarty_plugin_type modifier
     * @smarty_plugin_cacheable false
     * @usage `{$size|size_format:[ decimals => 3, decimal_separator => '.', thousands_separator => ' ', units =>  ]}`
     * Параметры передаются как массив:
     * - decimals - число знаков после запятой (3)
     * - decimal_separator - разделитель целой/дробной части (',')
     * - thousands_separator - разделитель тысяч (' ')
     * - units - юниты, ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
     *
     *
     *
     * @param int $size
     * @param array $params [int $decimals = 3, string $decimal_separator = '.', string $thousands_separator = ',']
     * @return string
     */
    public static function size_format(int $size, array $params):string
    {
        $decimals               = $params['decimals'] ?? 3;
        $decimal_separator      = $params['decimal_separator'] ?? '.';
        $thousands_separator    = $params['thousands_separator'] ?? ',';
        $units                  = $params['units'] ?? ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        if (empty($units)) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        }

        $index = min(floor((strlen(strval($size)) - 1) / 3), count($units) - 1);
        $number = number_format($size / pow(1000, $index), $decimals, $decimal_separator, $thousands_separator);
        return sprintf('%s %s', $number, $units[$index]);
    }

    /**
     * Dump and die
     *
     * @smarty_plugin_type modifier
     * @smarty_plugin_cacheable false
     * @usage `{$value|dd}`
     *
     * @return void
     */
    public static function dd(): void
    {
        if (php_sapi_name() !== "cli") echo '<pre>';
        if (func_num_args()) {
            foreach (func_get_args() as $arg) {
                var_dump($arg);
            }
        }
        if (php_sapi_name() !== "cli") echo '</pre>';

        die;
    }

    /**
     * Вставляет в шаблон значение переменной из окружения (getenv)
     *
     * @smarty_plugin_type function
     * @smarty_plugin_name _env
     * @smarty_plugin_cacheable false
     *
     * @usage `{_env key='ENV_VAR' default=250}`
     *
     * @param $params
     * @return void
     */
    public static function _env($params):string
    {
        $default = (empty($params['default'])) ? '' : $params['default'];
        if (empty($params['key'])) {
            return $default;
        }

        $k = getenv($params['key']);
        return ($k === false) ? $default : (string)$k;
    }

    /**
     * Форма числительного, параметры передаются массивом-списком.
     * Превращает число в строку, соответствующую его форме числительного.
     * Важно: не добавляет суффикс к числу, а заменяет число на строку!
     *
     * Дефолтного значения параметры не имеют, поэтому если соотв. форма будет пропущена,
     * то будет пустая строка.
     *
     * @smarty_plugin_type modifier
     * @smarty_plugin_cacheable false
     *
     * @usage `{$value} {$value|pluralForm:["Найдена","Найдено","Найдены"]}`
     *
     * @param $number
     * @param $forms
     * @return string
     */
    public static function pluralForm($number, $forms):string
    {
        $forms += ['', '', '']; // https://stackoverflow.com/a/17521426

        return
            ($number % 10 == 1 && $number % 100 != 11)
                ? $forms[0]
                : (
            ($number % 10 >= 2 && $number % 10 <= 4 && ($number % 100 < 10 || $number % 100 >= 20))
                ? $forms[1]
                : $forms[2]
            );
    }




}