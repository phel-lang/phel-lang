<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->files()
    ->in(__DIR__ . '/src/php')
    ->in(__DIR__ . '/tests/php')
    ->exclude(['out', 'PhelGenerated']);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'backtick_to_shell_exec' => true,
        'single_space_around_construct' => true,
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,
        'no_multiple_statements_per_line' => true,
        'braces_position' => true,
        'statement_indentation' => true,
        'no_extra_blank_lines' => true,
        'class_definition' => ['single_line' => true],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'elseif' => true,
        'encoding' => true,
        'ereg_to_preg' => true,
        'explicit_string_variable' => true,
        'fully_qualified_strict_types' => true,
        'type_declaration_spaces' => true,
        'general_phpdoc_annotation_remove' => [
            'annotations' => [
                'author',
                'package',
                'subpackage',
                'version',
            ],
        ],
        'global_namespace_import' => [
            'import_functions' => true,
        ],
        'include' => true,
        'increment_style' => ['style' => 'pre'],
        'list_syntax' => ['syntax' => 'short'],
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
        ],
        'native_type_declaration_casing' => true,
        'new_with_parentheses' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_homoglyph_names' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => ['use' => 'echo'],
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_short_bool_cast' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,
        'no_unused_imports' => true,
        'non_printable_character' => [
            'use_escape_sequences_in_strings' => true,
        ],
        'normalize_index_brace' => true,
        'object_operator_without_whitespace' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'php_unit_construct' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'multi'],
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_summary' => false,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,
        'self_accessor' => true,
        'single_quote' => true,
        'short_scalar_cast' => true,
        'standardize_increment' => true,
        'standardize_not_equals' => true,
        'static_lambda' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'switch_continue_to_break' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_elvis_operator' => true,
        'ternary_to_null_coalescing' => true,
        'trailing_comma_in_multiline' => [
            'elements' => [
                'arrays',
                'arguments',
                'parameters',
            ],
        ],
        'trim_array_spaces' => true,
        'unary_operator_spaces' => true,
        'types_spaces' => true,
        'visibility_required' => true,
        'void_return' => true,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => null,
        ],
    ]);
