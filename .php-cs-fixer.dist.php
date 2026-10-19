<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

// Same rules as in Achievia.
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'class_definition' => [
            'inline_constructor_arguments' => false,
            'multi_line_extends_each_single_line' => true,
            'single_item_single_line' => false,
            'single_line' => false,
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'method_argument_space' => [
            'after_heredoc' => true,
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'method_chaining_indentation' => true,
        'native_function_invocation' => [
            'include' => [],
            'scope' => 'namespaced',
            'strict' => false,
        ],
        'operator_linebreak' => [
            'only_booleans' => false,
            'position' => 'beginning',
        ],
        'declare_strict_types' => true,
        'php_unit_method_casing' => [
            'case' => 'snake_case',
        ],
        'phpdoc_align' => false,
        'single_line_throw' => false,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => [
                'arguments',
                'array_destructuring',
                'arrays',
                'match',
                'parameters',
            ],
        ],
    ])
    ->setFinder($finder)
;
