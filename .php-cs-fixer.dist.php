<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => [
            'statements' => [
                'for',
                'foreach',
                'if',
                'switch',
                'while',
                'try',
                'declare',
                'return',
            ],
        ],
        'cast_spaces' => ['space' => 'single'],
        'class_attributes_separation' => [
            'elements' => ['trait_import' => 'none'],
        ],
        'class_definition' => false,
        'single_import_per_statement' => false,
        'group_import' => true,
        'declare_strict_types' => true,
        'no_break_comment' => false,
        'phpdoc_separation' => false,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_to_comment' => false,
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
            ],
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => false,
            'import_constants' => false,
        ],
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],
        'types_spaces' => ['space' => 'none'],
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        'concat_space' => ['spacing' => 'one'],
        'no_empty_statement' => true,
        'no_superfluous_phpdoc_tags' => true,
        'void_return' => true,
        'simplified_null_return' => true,
        'static_lambda' => true,
        'use_arrow_functions' => true,
        'linebreak_after_opening_tag' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'method_public_abstract',
                'method_protected_abstract',
                'method_private_abstract',
                'method_public',
                'method_protected',
                'method_private',
                'method_public_final',
                'method_protected_final',
                'method_private_final',
            ],
        ],
        'php_unit_construct' => true,
        'php_unit_method_casing' => ['case' => 'camel_case'],
        'strict_comparison' => true,
        'no_unused_imports' => true,
    ])
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
