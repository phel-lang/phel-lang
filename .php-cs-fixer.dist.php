<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use PhpCsFixer\Config;

$finder = Finder::create()
    ->files()
    ->in(__DIR__ . '/src/php')
    ->in(__DIR__ . '/tests/php');

return (new Config())
  ->setFinder($finder)
  ->setRules([
    '@PSR2' => true,
    'array_syntax' => ['syntax' => 'short'],
    'blank_line_after_opening_tag' => true,
    'braces' => ['allow_single_line_closure' => true],
    'compact_nullable_typehint' => true,
    'concat_space' => ['spacing' => 'one'],
    'declare_equal_normalize' => ['space' => 'none'],
    'declare_strict_types' => true,
    'function_typehint_space' => true,
    'list_syntax' => ['syntax' => 'short'],
    'new_with_braces' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_trailing_comma_in_singleline_array' => true,
    'no_whitespace_before_comma_in_array' => true,
    'no_unused_imports' => true,
    'no_whitespace_in_blank_line' => true,
    'normalize_index_brace' => true,
    'ordered_imports' => true,
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
    'return_type_declaration' => ['space_before' => 'none'],
    'single_trait_insert_per_statement' => true,
    'single_quote' => true,
    'trailing_comma_in_multiline' => [
        'elements' => ['arrays'],
    ],
    'trim_array_spaces' => true,
    'void_return' => true,
  ]);
