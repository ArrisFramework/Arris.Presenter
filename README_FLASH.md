# Slim Framework Flash Messages

Flash messages enables you to define transient messages that persist only from the current request to the next request.

## Install

Via Composer

``` bash
$ composer require ajur-media/template.flash
```

## Usage

### Slim 3

```php
// Start PHP session
if (!session_id()) @session_start();

App::$flash = new \AJUR\Template\FlashMessages();

// or use DI Container

$app->get('/foo', function ($req, $res, $args) {
    // Set flash message for next request
    App::$flash->addMessage('Test', 'This is a message');

    // Redirect
    return $res->withStatus(302)->withHeader('Location', '/bar');
});

$app->get('/bar', function ($req, $res, $args) {
    // Get flash messages from previous request
    $messages = $this->flash->getMessages();
    print_r($messages);

    // Get the first message from a specific key
    $test = $this->flash->getFirstMessage('Test');
    print_r($test);
});

$app->run();
```

> Please note that a message could be a string, object or array. Please check what your storage can handle.

## Testing

``` bash
$ phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
