# Todo

## Регистрация Smarty-плагинов

Хорошо бы дополнить этот механизм возможность регистрировать плагином произвольную функцию произвольного класса:

```php
\AJUR\TemplatePlugins::register($SMARTY, [ "size_format", "MyClass::convert", "_func" => "MyClass::func" ]);
```

Как мы можем передавать функцию:
- 1) строка - ищется сначала в TemplatePlugins::method, потом method в глобальной области видимости среди функций
- 2) массив [class, method]
- 3) хорошо бы расширенный строковый формат "Class::method" -> [Class, method]

- добавить обработку параметра `@smarty_plugin_name` - имя, под которым регистрируем метод. По умолчанию совпадает с именем функции.

## Flash-messages

Добавить flash-messages, см как сделано в `slim/flash`

`->setFlashMessage($code, $message)`

`->getFlashMessages():array`

`->assingFlashMessages($value = 'flash_messages')`

Вердикт: отказались, потому что этот механизм не имеет отношения к шаблонизатору и обертке над ним. 



