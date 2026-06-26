<?php declare(strict_types=1);

require_once __DIR__ . '/.php-cs-fixer/DeclareStrictTypesOneLineFixer.php';
require_once __DIR__ . '/.php-cs-fixer/ClassSectionSeparationFixer.php';

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/src',
        __DIR__ . '/functions',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'var',
        'vendor',
    ]);

return (new Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->registerCustomFixers([new DeclareStrictTypesOneLineFixer(), new ClassSectionSeparationFixer()])
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,

        // --- Modern PHP / Symfony ---
        'declare_strict_types' => true,
        'blank_line_after_opening_tag' => false,
        'App/declare_strict_types_one_line' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,

        // --- Arrays ---
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],

        // --- Class structure ---
        'no_blank_lines_after_class_opening' => false,
        // Handled by our custom fixer — disable to avoid conflict
        'class_attributes_separation' => false,
        // Allow 2 blank lines (section separators); 'extra' would collapse them to 1
        'no_extra_blank_lines' => [
            'tokens' => [
                'attribute',
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public', 'constant_protected', 'constant_private',
                'property_static_public', 'property_static_protected', 'property_static_private',
                'property_public', 'property_protected', 'property_private',
                'construct', 'destruct',
                'magic',
                'method_abstract',
                'method_public', 'method_public_static',
                'method_protected', 'method_protected_static',
                'method_private', 'method_private_static',
            ],
        ],

        'App/class_section_separation' => true,

        // --- Imports ---
        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => false,
            'import_constants' => false,
        ],
        'fully_qualified_strict_types' => true,

        // --- Readability ---
        'not_operator_with_successor_space' => false,
        'unary_operator_spaces' => true,
        'spaces_inside_parentheses' => true,

        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'single_line_throw' => false,

        'method_chaining_indentation' => true,

        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // --- Strings ---
        'single_quote' => true,

        // --- Braces ---
        'single_line_empty_body' => true,

        // --- Control structures ---
        'control_structure_braces' => true,
        'control_structure_continuation_position' => [
            'position' => 'same_line',
        ],

        // --- Docblocks ---
        'phpdoc_to_comment' => false,
        'phpdoc_line_span' => [
            'const' => 'multi',
            'property' => 'multi',
            'method' => 'multi',
        ],

        // --- Clean code ---
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_useless_sprintf' => true,

        // --- Risky but useful ---
        'strict_comparison' => true,
        'strict_param' => true,
        'void_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'modernize_types_casting' => true,
        'no_alias_functions' => true,

        // --- Spacing ---
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'concat_space' => ['spacing' => 'one'],
    ]);
