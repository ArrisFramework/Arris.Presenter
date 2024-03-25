


# ToDo

## Добавить механизм установки кастомных полей Smarty, что-то типа:

https://www.smarty.net/docsv2/ru/language.escaping.tpl

$tempate->setSmartyOption($key, $value) 

под капотом:

SmartyOptions[ $key ] = $value

при инициализации

```php
foreach (SmartyOptions as key as $value) {
    $smarty->{$key} = $value;
}
```
