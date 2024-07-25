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

## Механизм Breadcrumbs

Имеет смысл сделать через Stack. pushBreadCumbs($url, $title) - потому что хлебные крошки у нас имеют вид:
```html
<a href="/">Сайт</a>
<a href="/articles/">Статьи</a>
<a href="/articles/1234/">Конкретная статья</a>
```

То есть класть в стек нужно: url + title  

```php
require_once __DIR__ . '/vendor/autoload.php';

$s = new \Arris\Core\Stack();

$s->push([
    'url'   =>  '/',
    'title' =>  'Главная страница'
]);

$s->push([
    'url'   =>  '/articles/',
    'title' =>  'Все статьи'
]);

$s->push([
    'url'   =>  '/articles/123/',
    'title' =>  'Моя статья'
]);

$s->reverse();

$string = "\n\n";
do {
    $el = $s->pop();

    $string .= <<<STR
<a href="{$el['url']}">{$el['title']}</a>\n
STR;
} while (!$s->isEmpty());

var_dump($string);
```

Результат:
```html
<a href="/">Главная страница</a>
<a href="/articles/">Все статьи</a>
<a href="/articles/123/">Моя статья</a>
```
just as planned


