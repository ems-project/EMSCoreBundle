<?php

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@Symfony' => true,
        'native_function_invocation' => ['include' => ['@all']],
        'no_unused_imports' => true
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ;
