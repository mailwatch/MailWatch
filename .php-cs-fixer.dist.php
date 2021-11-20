<?php

$finder = (new PhpCsFixer\Finder())
    ->in('mailscanner')->exclude('lib')
    ->in('tools')

;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        '@PHP54Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'native_function_invocation' => false,
        'no_superfluous_phpdoc_tags' => true,
    ])
    ->setFinder($finder)
;
