<?php

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->exclude('tests/tmp')
;

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@Symfony' => true,
        //'declare_strict_types' => true,
        //'final_class' => true,
        'native_function_invocation' => ['include' => ['@all']],
        'no_unused_imports' => true
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
