<?php

$finder = (new PhpCsFixer\Finder())
    ->in('mailscanner')->exclude('lib')
    ->in('tools')
;

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        '@PHP8x1Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'native_function_invocation' => false,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true],
        'fopen_flags' => ['b_mode' => true],
        'function_declaration' => ['closure_function_spacing' => 'none', 'closure_fn_spacing' => 'none'],
        'phpdoc_summary' => false,
        'phpdoc_no_package' => false,
        'phpdoc_separation' => ['groups' => [['ORM\\*'], ['Assert\\*'], ['Serializer\\*']]],
        'fully_qualified_strict_types' => false,
    ])
;
