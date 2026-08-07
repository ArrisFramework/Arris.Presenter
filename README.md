# Arris.Presenter - wrapper over template engine "Smarty"

Ленивая инициализация Smarty. Один инстанс может выступать «главным» для финального вывода,
остальные - для промежуточных рендеров «в переменную» (HTML/JSON/RAW/Result).

```php
$t = new \Arris\Presenter\Template(smarty_options: [], template_options: [], logger: null);
```

## Статические дефолты и фабрики

Чтобы не регистрировать каталоги/плагины/классы/хуки для каждого инстанса заново,
задайте дефолты один раз и создавайте инстансы через фабрику:

```php
use Arris\Presenter\Template;

Template::setDefaults([
    'setTemplateDir'    => '/path/to/templates',
    'setCompileDir'     => '/path/to/cache',
    'setForceCompile'   => true,
    'cleanup_extra_eol' => true,
]);
Template::addPlugin(Template::PLUGIN_MODIFIER, 'json_decode', 'json_decode');
Template::addPlugin(Template::PLUGIN_MODIFIER, 'json_encode', 'json_encode');
Template::addClass('Arris\AppRouter', 'Arris\AppRouter');
Template::addHook('pre_content', $someCallback);
```

- `Template::setDefaults(array $config)` - мержит переданное в статические дефолты.
  Массив **плоский**: ключи `setTemplateDir`/`setCompileDir`/`setForceCompile`/`setConfigDir`
  маршрутизируются в smarty-опции автоматически, остальные - в template-опции.
  Возвращает fluent-билдер `Arris\Presenter\System\TemplateDefaultsBuilder`, поэтому можно строить цепочку:

  ```php
  Template::setDefaults([...])
      ->registerClass('Arris\AppRouter', 'Arris\AppRouter')
      ->registerHook('pre_content', $someCallback)
      ->registerPlugin(Template::PLUGIN_MODIFIER, 'json_decode', 'json_decode');
  ```

  Методы билдера пишут в те же дефолты, что и `add*` ниже. Билдер - отдельный класс:
  PHP запрещает одноимённые static и instance-методы в одном классе, а у `Template`
  инстансные `registerPlugin()`/`registerClass()`/`registerHook()` уже заняты.
- `Template::applyOption(string $name, mixed $value)` - точечное обновление одного дефолт-опшена.
- `Template::addPlugin(...)` / `addClass(...)` / `addHook(...)` - аддитивные регистрации в дефолты
  (те же, что использует билдер). Ключи `plugins`/`classes`/`hooks` в `setDefaults()`
  зарезервированы - только через `add*` или билдер.
- `Template::make(array $options = [], $logger = null)` - чистый инстанс на основе дефолтов;
  принимает тот же плоский массив опций, опции инстанса имеют приоритет над дефолтами.
- `$t->fork()` - изолированный дочерний инстанс по образцу текущего: наследует конфигурацию,
  плагины, классы и хуки родителя, но сбрасывает состояние (шаблон, assigned-переменные, хедеры,
  редирект, рендер-тип). Smarty пересоздается лениво.
  **Нюанс именования:** `spawn()` - исторический алиас `fork()` (метод-первоисточник теперь `fork()`);
  оба эквивалентны, `spawn()` сохранен для обратной совместимости.

Промежуточный рендер «в переменную»:

```php
$partial = Template::make();               // или $main->fork()
$partial->setTemplateContent('...');
$html = $partial->renderToString();        // не шлёт хедеры, не печатает

$json = Template::make();
$json->assignJSON($data);
$api = $json->renderToString();
```

## render() и хедеры

`render()` по умолчанию **не отправляет** хедеры - только возвращает строку.
Отправка - только явная: `render(send_headers: true)` либо `$template->headers->send()`.

```php
$render = $template->render();           // заголовки НЕ отправлены
if (!empty($render)) {
    $template->headers->send();
    echo $render;
}
if ($template->isRedirect()) {
    $template->makeRedirect();
}
```

`renderToString()` - алиас `render(false)`, удобен для промежуточных рендеров.

## Типы контента (enum ContentType)

Тип рендера задается `setRenderType()` и хранится как `Arris\Presenter\System\ContentType`
(backed-string enum) — единый источник истины для хедеров контента и HTTP-статусов.
Старые константы `Template::CONTENT_TYPE_*` сохранены как алиасы на кейсы enum
(`Template::CONTENT_TYPE_HTML === ContentType::HTML`), так что существующий код не меняется.
`setRenderType()` принимает и enum, и строку (`'json'`, `'404'` — по `tryFrom`);
неизвестная строка бросает `InvalidArgumentException`.

```php
use Arris\Presenter\System\ContentType;

$t->setRenderType(Template::CONTENT_TYPE_404);  // BC-алиас
$t->setRenderType(ContentType::JSON);           // enum напрямую

ContentType::JSON->contentTypeHeader();   // 'application/json; charset=utf-8'
ContentType::NOT_FOUND->statusCode();     // 404
ContentType::HTML->needsTemplateRender(); // true
```

## smarty_options:

Ключевые опции Smarty, применяемые при ленивой инициализации:
`setTemplateDir`, `setCompileDir`, `setForceCompile`, `setConfigDir`
(задаются либо через конструктор, либо методами-цепочками `setTemplateDir()` и т.п.).
Произвольные нативные свойства Smarty - через `setSmartyNativeOption($key, $value)`.

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

### Комментарии Smarty и пробелы (`cleanup_extra_eol`)

Smarty при удалении комментария `{* ... *}` вырезает сам комментарий и один перевод
строки после него, но **пробелы перед ним остаются** и прилипают к началу следующей
строки вывода. Нативной опции «игнорировать строки из одних пробелов» у Smarty нет.

`cleanup_extra_eol` (по умолчанию `true`) - пост-обработка результата рендера
regex'ом `/^\h*\v+/m`, удаляющим строки, состоящие целиком из пробелов. Поэтому он
чинит только ту утечку, которая попадает в **пустую строку**; если комментарий идёт
сразу за выводимым тегом и после него нет перевода строки, пробелы прилипают
к строке с содержимым и regex их не видит.

Надёжный паттерн шаблона - каждый комментарий на своей строке обязательно
завершать пустой строкой:

```html
    {$meta}

    {* любой комментарий на своей строке *}

</head>
```

Альтернатива - комментарий от колонки 0 (без отступа): тогда прилипать нечему.

## FlashMessages

Класс реализует паттерн flash-сообщений (наследие Slim Flash) с **двумя очередями**:

- `addMessage($key, $message)` - сообщение появится в **следующем** запросе (хранится в сессии);
- `addMessageNow($key, $message)` - сообщение видно **в текущем** запросе (в памяти).

Получить: `getMessages()` (все), `getMessage($key, $default)` (все по ключу),
`getFirstMessage($key, $default)` (первое по ключу), `hasMessage($key)`.
Очистка: `clearMessages()` / `clearMessage($key)`.

Если ключ не нужен (одна очередь) - ключ можно опустить: один параметр трактуется как
сообщение в дефолтный ключ `FlashMessages::DEFAULT_KEY` (`'flash'`):

```php
$flash = FlashMessages::getInstance();   // синглтон; требует активной $_SESSION

$flash->addMessage(['type' => 'success', 'text' => 'Сохранено']);   // === addMessage('flash', ...)
$flash->addMessageNow('Это видно сразу');

$messages = $flash->getMessage('flash', []);   // что показать в шаблоне
```

Ключ (`'flash'`, `'errors'`, `'success'`, ...) позволяет в одной сессии держать
несколько **независимых** очередей сообщений. Если очередь всегда одна - везде
работает единый `DEFAULT_KEY`.

Синглтон `getInstance()` работает только с `$_SESSION` (иначе `RuntimeException`).
Альтернатива - своё хранилище: `new FlashMessages($storage)`, где `$storage` -
массив или `\ArrayAccess` (по ссылке).

## Meta

Мета-данные веб-страницы (стандартные теги, OpenGraph, Twitter Cards). Экземпляр
`Meta` создаётся презентером eagerly и доступен как `$template->meta` (сбрасывается
в `fork()`):

```php
$template->meta
    ->setTitle('Заголовок страницы')
    ->setDescription('Краткое описание')
    ->setCanonical('https://example.com/page')
    ->setOgType('article')
    ->setOgImage('https://example.com/img.png')
    ->addCustom('google-site-verification', 'abc123');

$html = $template->meta->render();            // готовые <title>/<meta>/<link canonical>
$data = $template->meta->toArray();           // структурированный массив
$template->assign('meta', $template->meta->toArray());
```

Стандартные: `title, description, keywords, robots, canonical, author`.
OpenGraph: `og:type` (по умолчанию `website`), `og:title`, `og:description`,
`og:image`, `og:url`, `og:site_name`, `og:locale` (`ru_RU`),
`og:locale:alternate` (несколько - `addOgLocaleAlternate()`).
Twitter Cards: `twitter:card` (`summary`), `twitter:image`.
Произвольные теги - `addCustom($name, $content)`.

Fallback, если OG не задан явно: `og:title <- title`, `og:description <- description`,
`og:url <- canonical`; `twitter:title/description` берут те же значения,
`twitter:image <- og:image`.

`render()` возвращает строку `<title>` + `<meta>` + `<link rel="canonical">`,
экранируя значения через `htmlspecialchars(ENT_QUOTES)`. Теги выводятся по одному
на строку; первая строка без отступа, каждая последующая получает префикс из
`$indent` символов `$char` (`render(int $indent = 4, string $char = ' ')`).
Так блок выравнивается по отступу шаблона:

```php
{$meta}   // отступ шаблона в 4 пробела -> render() по умолчанию выровняет все строки
{$template->meta->render(8, ' ')}   // свой отступ
{$template->meta->render(0)}        // без отступа
```

Очистка - `clear()`. Реализует `MetaInterface`, доступен и автономно: `new Meta()`.
