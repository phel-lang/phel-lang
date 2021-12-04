<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->in(__DIR__ . '/src/php')
    ->in(__DIR__ . '/tests/php')
    ->exclude('out');

return (new Config())
  ->setFinder($finder)
  ->setRiskyAllowed(true)
  ->setRules([
    '@PSR12' => true,
    'array_syntax' => ['syntax' => 'short'],
    'braces' => [
        'allow_single_line_closure' => true,
        'allow_single_line_anonymous_class_with_empty_body' => true,
    ],
    'concat_space' => ['spacing' => 'one'],
    'declare_strict_types' => true,
    'function_typehint_space' => true,
    'list_syntax' => ['syntax' => 'short'],
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_leading_namespace_whitespace' => true,
    'no_trailing_comma_in_singleline_array' => true,
    'no_whitespace_before_comma_in_array' => true,
    'no_unused_imports' => true,
    'normalize_index_brace' => true,
    'ordered_imports' => [
        'imports_order' => [
            'class',
            'function',
            'const',
        ],
        'sort_algorithm' => 'alpha',
    ],
    'php_unit_method_casing' => ['case' => 'snake_case'],
    'phpdoc_add_missing_param_annotation' => true,
    'phpdoc_annotation_without_dot' => true,
    'phpdoc_indent' => true,
    'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'multi'],
    'phpdoc_order' => true,
    'phpdoc_scalar' => true,
    'phpdoc_separation' => true,
    'phpdoc_summary' => true,
    'phpdoc_trim' => true,
    'phpdoc_types' => true,
    'phpdoc_var_annotation_correct_order' => true,
    'phpdoc_var_without_name' => true,
    'single_quote' => true,
    'trailing_comma_in_multiline' => [
        'elements' => ['arrays'],
    ],
    'trim_array_spaces' => true,
    'void_return' => true,
  ]);
