# Arris.Presenter - wrapper over template engine "Smarty"

Используется ленивая инициализация

```php
$t = new \Arris\Presenter\Template(smarty_options: [], template_options: [], logger: null);
```

## smarty_options:


## template_options:

- `file` or `source` - глобальный файл шаблона, устанавливаемый при инициализации (null);
- `cleanup_extra_eol` - убирать ли лишние переводы строк при рендере (true);
- `hook_disable_named_params` (false) - отключить ли именованные параметры для хуков?
- `ignore_undefined_hooks` (true) - игнорировать неопределенные хуки: если метод хука не найден/не определен - возвращаем пустую строку как результат хука

Отключение именованных параметров для хуков позволяет избежать ошибки вида `"Uncaught Error: Unknown named parameter $foo"`
Она возникнет в PHP8, если запись хука будет вида:
```php
{hook run='pre_content' foo=$foo}
```
... но в обработчике хука не будет именованного параметра `$foo`. 

Эта ошибка - следствие обратно-несовместимого изменения методов `call_user_func*` в PHP8: 
https://dev.to/seongbae/unknown-named-parameter-2gln

(In PHP 7, the keys in $params were ignored. However, in PHP 8, they are not - keys are converted to named parameters.)

Отключение ошибки достигается применением `array_values()` к списку параметров.

P.S. На самом деле это решается прямым указанием значений по-умолчанию в обработчике хука:
```php
->registerHook('pre_content', function ($foo = 'aaa'){
    return "pre content hook with arg: {$foo}";
})
```
Тогда 
```php
{hook run='pre_content' foo=$foo}
{hook run='pre_content'}
```
отрабатывают корректно оба.


