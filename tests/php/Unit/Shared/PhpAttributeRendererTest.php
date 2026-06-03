<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Shared\PhpAttributeRenderer;
use PHPUnit\Framework\TestCase;

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

    private function vec(mixed ...$values): mixed
    {
        return TypeFactory::getInstance()->persistentVectorFromArray($values);
    }

    private function map(mixed ...$kvs): mixed
    {
        return TypeFactory::getInstance()->persistentMapFromKVs(...$kvs);
    }
}
