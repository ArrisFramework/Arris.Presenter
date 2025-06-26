<?php

use Arris\Presenter\Template;

require_once __DIR__ . '/../vendor/autoload.php';

class XXX {
    public function YYY($foo = 'aaa'): string {
        return __METHOD__ . " -> pre content hook with arg: {$foo} " . PHP_EOL;
    }
}

$t = new Template();
$t
    ->setTemplateDir(__DIR__ )
    ->setCompileDir(__DIR__ . '/cache/')
    ->setForceCompile(true)
    ->setEngineOption('hook_disable_named_params', false)
    ->registerHook('pre_content', [ \XXX::class, 'YYY' ])
    ->registerHook('pre_content', function ($foo = 'aaa'){
        return "Callback -> pre content hook with arg: {$foo}"  . PHP_EOL;
    })
    ->registerHook('post_content', function ($id = 5){
        return "Post content {$id}" . PHP_EOL;
    });
;

$t->setTemplate('outer_include_render.tpl');
$t->assign('content', 'Example content');
$t->assign('foo', '123');
$t->assign('bar', 456);
$t->assign('config', [
    'aaa'   =>  'bbb',
    'ddd'   =>  123
]);

if (!empty($render = $t->render())) {
    echo $render;
}





