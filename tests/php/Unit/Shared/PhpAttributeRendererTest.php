<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Shared\PhpAttributeRenderer;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PhpAttributeRendererTest extends TestCase
{
    private PhpAttributeRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PhpAttributeRenderer();
    }

    public function test_non_vector_input_renders_nothing(): void
    {
        self::assertSame([], $this->renderer->render(null));
        self::assertSame([], $this->renderer->render('not-a-vector'));
    }

    public function test_empty_vector_renders_nothing(): void
    {
        self::assertSame([], $this->renderer->render($this->vec()));
    }

    public function test_bare_attribute_without_namespace(): void
    {
        $specs = $this->vec($this->vec(Keyword::create('Route')));

        self::assertSame(['#[\Route]'], $this->renderer->render($specs));
    }

    public function test_namespaced_attribute(): void
    {
        $specs = $this->vec($this->vec(Keyword::create('Column', 'ORM')));

        self::assertSame(['#[\ORM\Column]'], $this->renderer->render($specs));
    }

    public function test_bare_keyword_is_a_single_zero_arg_attribute(): void
    {
        self::assertSame(['#[\ORM\Entity]'], $this->renderer->render(Keyword::create('Entity', 'ORM')));
        self::assertSame(['#[\Route]'], $this->renderer->render(Keyword::create('Route')));
    }

    public function test_single_spec_vector_without_outer_wrapper(): void
    {
        // First element is the attribute-name keyword => one attribute.
        $specs = $this->vec(
            Keyword::create('Column', 'ORM'),
            $this->map(Keyword::create('length'), 255),
        );

        self::assertSame(['#[\ORM\Column(length: 255)]'], $this->renderer->render($specs));
    }

    public function test_single_spec_zero_arg_without_outer_wrapper(): void
    {
        $specs = $this->vec(Keyword::create('Entity', 'ORM'));

        self::assertSame(['#[\ORM\Entity]'], $this->renderer->render($specs));
    }

    public function test_dotted_namespace_maps_to_backslash(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Route', 'Symfony.Component.Routing.Attribute'),
        ));

        self::assertSame(
            ['#[\Symfony\Component\Routing\Attribute\Route]'],
            $this->renderer->render($specs),
        );
    }

    public function test_positional_string_argument(): void
    {
        $specs = $this->vec($this->vec(Keyword::create('Route'), '/products/{id}'));

        self::assertSame(["#[\Route('/products/{id}')]"], $this->renderer->render($specs));
    }

    public function test_named_arguments_from_map(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Column', 'ORM'),
            $this->map(Keyword::create('length'), 255),
        ));

        self::assertSame(['#[\ORM\Column(length: 255)]'], $this->renderer->render($specs));
    }

    public function test_positional_and_named_and_vector_argument(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Route'),
            '/p',
            $this->map(Keyword::create('methods'), $this->vec('GET', 'POST')),
        ));

        self::assertSame(
            ["#[\Route('/p', methods: ['GET', 'POST'])]"],
            $this->renderer->render($specs),
        );
    }

    public function test_boolean_argument(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Entity', 'ORM'),
            $this->map(Keyword::create('readOnly'), true),
        ));

        self::assertSame(['#[\ORM\Entity(readOnly: true)]'], $this->renderer->render($specs));
    }

    public function test_multiple_attributes(): void
    {
        $specs = $this->vec(
            $this->vec(Keyword::create('Id', 'ORM')),
            $this->vec(Keyword::create('Column', 'ORM')),
        );

        self::assertSame(
            ['#[\ORM\Id]', '#[\ORM\Column]'],
            $this->renderer->render($specs),
        );
    }

    public function test_spec_without_keyword_name_is_skipped(): void
    {
        $specs = $this->vec($this->vec('not-a-keyword'));

        self::assertSame([], $this->renderer->render($specs));
    }

    public function test_non_vector_spec_in_list_is_skipped(): void
    {
        // Outer is a list-of-specs (first element is itself a vector), but one
        // entry is a non-vector keyword spec and must be dropped silently.
        $specs = $this->vec(
            $this->vec(Keyword::create('Id', 'ORM')),
            Keyword::create('not-a-spec'),
        );

        self::assertSame(['#[\ORM\Id]'], $this->renderer->render($specs));
    }

    public function test_nested_map_value_renders_as_php_array(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Column', 'ORM'),
            $this->map(
                Keyword::create('options'),
                $this->map(Keyword::create('default'), 0),
            ),
        ));

        self::assertSame(
            ["#[\\ORM\\Column(options: ['default' => 0])]"],
            $this->renderer->render($specs),
        );
    }

    public function test_nested_vector_of_vectors_renders_nested_arrays(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Route'),
            $this->vec($this->vec('GET'), $this->vec('POST')),
        ));

        self::assertSame(
            ["#[\\Route([['GET'], ['POST']])]"],
            $this->renderer->render($specs),
        );
    }

    public function test_float_argument_is_rendered(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Range'),
            $this->map(Keyword::create('min'), 1.5),
        ));

        self::assertSame(['#[\Range(min: 1.5)]'], $this->renderer->render($specs));
    }

    public function test_keyword_value_renders_as_string(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Column', 'ORM'),
            $this->map(Keyword::create('type'), Keyword::create('integer')),
        ));

        self::assertSame(["#[\\ORM\\Column(type: 'integer')]"], $this->renderer->render($specs));
    }

    public function test_unsupported_value_renders_as_null(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Thing'),
            $this->map(Keyword::create('obj'), new stdClass()),
        ));

        self::assertSame(['#[\Thing(obj: null)]'], $this->renderer->render($specs));
    }

    public function test_non_keyword_map_key_is_skipped(): void
    {
        $specs = $this->vec($this->vec(
            Keyword::create('Thing'),
            $this->map('plainKey', 1, Keyword::create('kept'), 2),
        ));

        self::assertSame(['#[\Thing(kept: 2)]'], $this->renderer->render($specs));
    }

    public function test_empty_inner_spec_vector_is_skipped(): void
    {
        // Outer list-of-specs containing one valid spec and one empty vector
        // (no name keyword) which must be dropped.
        $specs = $this->vec(
            $this->vec(Keyword::create('Id', 'ORM')),
            $this->vec(),
        );

        self::assertSame(['#[\ORM\Id]'], $this->renderer->render($specs));
    }

    private function vec(mixed ...$values): mixed
    {
        return TypeFactory::getInstance()->persistentVectorFromArray($values);
    }

    private function map(mixed ...$kvs): mixed
    {
        return TypeFactory::getInstance()->persistentMapFromKVs(...$kvs);
    }
}
