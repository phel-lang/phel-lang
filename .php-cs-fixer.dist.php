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
    ->setUnsupportedPhpVersionAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        // ------------------------------------------------------------------
        // Base preset
        // ------------------------------------------------------------------
        '@PSR12' => true,

        // ------------------------------------------------------------------
        // Language level & typing
        // ------------------------------------------------------------------
        'declare_strict_types' => true,
        'encoding' => true,
        'fully_qualified_strict_types' => true,
        'native_type_declaration_casing' => true,
        'type_declaration_spaces' => true,
        'void_return' => true,

        // ------------------------------------------------------------------
        // Control structures & flow
        // ------------------------------------------------------------------
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,
        'elseif' => true,
        'no_multiple_statements_per_line' => true,
        'no_useless_else' => true,
        'standardize_not_equals' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'switch_continue_to_break' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_elvis_operator' => true,
        'ternary_to_null_coalescing' => true,
        'braces_position' => true,

        // ------------------------------------------------------------------
        // Classes, methods & visibility
        // ------------------------------------------------------------------
        'class_definition' => ['single_line' => true],
        'no_blank_lines_after_class_opening' => true,
        'ordered_class_elements' => true,
        'self_accessor' => true,
        'visibility_required' => true,

        // ------------------------------------------------------------------
        // Functions & invocations
        // ------------------------------------------------------------------
        'global_namespace_import' => ['import_functions' => true],
        'include' => true,
        'increment_style' => ['style' => 'pre'],
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'new_with_parentheses' => true,
        'static_lambda' => true,

        // ------------------------------------------------------------------
        // Namespaces & imports ordering
        // ------------------------------------------------------------------
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],

        // ------------------------------------------------------------------
        // Arrays & list syntax
        // ------------------------------------------------------------------
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],
        'normalize_index_brace' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_whitespace_before_comma_in_array' => true,
        'trim_array_spaces' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],

        // ------------------------------------------------------------------
        // Strings, echoes & operators
        // ------------------------------------------------------------------
        'backtick_to_shell_exec' => true,
        'concat_space' => ['spacing' => 'one'],
        'explicit_string_variable' => true,
        'non_printable_character' => ['use_escape_sequences_in_strings' => true],
        'no_mixed_echo_print' => ['use' => 'echo'],
        'short_scalar_cast' => true,
        'single_quote' => true,
        'standardize_increment' => true,

        // ------------------------------------------------------------------
        // Whitespace, spacing & formatting
        // ------------------------------------------------------------------
        'no_extra_blank_lines' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_whitespace_in_blank_line' => true,
        'object_operator_without_whitespace' => true,
        'single_space_around_construct' => true,
        'statement_indentation' => true,
        'unary_operator_spaces' => true,

        // ------------------------------------------------------------------
        // PHPDoc consistency
        // ------------------------------------------------------------------
        // Remove noisy author/package/version tags across the codebase
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['author', 'package', 'subpackage', 'version'],
        ],
        // Align @param/@return/@throws neatly
        'phpdoc_align' => [
            'align' => 'vertical',
            'tags' => ['param', 'return', 'throws', 'var'],
        ],
        // Canonical tag order and casing
        'phpdoc_order' => true,
        'phpdoc_tag_casing' => ['tags' => ['inheritDoc' => 'inheritDoc']],
        // Normalize type lists and always put null last (e.g., int|string|null)
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'phpdoc_types' => true,
        'phpdoc_scalar' => true,
        // Tighten spacing/blank lines inside blocks
        'phpdoc_indent' => true,
        'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'multi'],
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_annotation_without_dot' => true,
        // Add missing @param when it’s absent (for non-typed params)
        'phpdoc_add_missing_param_annotation' => true,
        // Keep only meaningful @param/@return/@var; avoids duplicates with native types
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'allow_unused_params' => false,
            'allow_hidden_params' => false,
        ],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,
        'no_empty_phpdoc' => true,
        'no_empty_comment' => true,
        // Prefer comments (/** → /*) when it’s not real PHPDoc
        'phpdoc_to_comment' => false,

        // ------------------------------------------------------------------
        // PHPUnit rules
        // ------------------------------------------------------------------
        'php_unit_construct' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],

        // ------------------------------------------------------------------
        // Miscellaneous
        // ------------------------------------------------------------------
        'ereg_to_preg' => true,
        'no_empty_statement' => true,
        'no_homoglyph_names' => true,
        'no_useless_return' => true,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => null],
    ]);
