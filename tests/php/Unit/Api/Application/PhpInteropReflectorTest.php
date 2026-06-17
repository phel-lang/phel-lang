<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpInteropReflector;
use Phel\Api\Transfer\Completion;
use PhelTest\Unit\Api\Application\Fixtures\SignatureFixture;
use PHPUnit\Framework\TestCase;

use function array_map;

final class PhpInteropReflectorTest extends TestCase
{
    private PhpInteropReflector $reflector;

    protected function setUp(): void
    {
        $this->reflector = new PhpInteropReflector();
    }

    public function test_instance_members_complete_for_known_class(): void
    {
        $labels = $this->labels($this->reflector->instanceMembers('\\DateTimeImmutable', 'get'));

        self::assertContains('getTimestamp', $labels);
        self::assertContains('getOffset', $labels);
    }

    public function test_instance_members_carry_a_signature_detail(): void
    {
        $members = $this->reflector->instanceMembers('\\DateTimeImmutable', 'getTimestamp');

        self::assertNotSame([], $members);
        self::assertStringContainsString('getTimestamp(', $members[0]->detail);
    }

    public function test_instance_members_exclude_static_and_magic(): void
    {
        $labels = $this->labels($this->reflector->instanceMembers('\\DateTimeImmutable'));

        self::assertNotContains('createFromFormat', $labels, 'static method excluded from instance members');
        self::assertNotContains('__construct', $labels, 'magic method excluded');
    }

    public function test_static_members_complete_static_methods(): void
    {
        $labels = $this->labels($this->reflector->staticMembers('\\DateTimeImmutable', 'create'));

        self::assertContains('createFromFormat', $labels);
    }

    public function test_static_members_include_constants(): void
    {
        $labels = $this->labels($this->reflector->staticMembers('\\DateTimeImmutable', 'ATOM'));

        self::assertContains('ATOM', $labels);
    }

    public function test_unknown_class_yields_empty_completions(): void
    {
        self::assertSame([], $this->reflector->instanceMembers('\\This\\Does\\Not\\Exist'));
        self::assertSame([], $this->reflector->staticMembers('\\This\\Does\\Not\\Exist'));
        self::assertNull($this->reflector->methodSignature('\\Nope', 'foo'));
    }

    public function test_class_names_complete_from_declared_classes(): void
    {
        $labels = $this->labels($this->reflector->classNames('DateTimeImm'));

        self::assertContains('DateTimeImmutable', $labels);
    }

    public function test_class_names_tolerate_leading_backslash_prefix(): void
    {
        $labels = $this->labels($this->reflector->classNames('\\DateTimeImm'));

        self::assertContains('DateTimeImmutable', $labels);
    }

    public function test_global_functions_complete_and_carry_signature(): void
    {
        self::assertContains('str_replace', $this->labels($this->reflector->globalFunctions('str_rep')));

        $completions = $this->reflector->globalFunctions('strrev');
        self::assertNotSame([], $completions);
        self::assertStringContainsString('strrev(', $completions[0]->detail);
    }

    public function test_method_signature_for_known_method(): void
    {
        $signature = $this->reflector->methodSignature('\\DateTimeImmutable', 'setTimestamp');

        self::assertNotNull($signature);
        self::assertStringContainsString('setTimestamp(', $signature);
    }

    public function test_function_signature_for_known_function(): void
    {
        $signature = $this->reflector->functionSignature('strlen');

        self::assertNotNull($signature);
        self::assertStringContainsString('strlen(', $signature);
        self::assertStringContainsString(': int', $signature);
    }

    public function test_function_signature_null_for_unknown(): void
    {
        self::assertNull($this->reflector->functionSignature('this_function_does_not_exist_zzz'));
    }

    public function test_method_signature_info_exposes_parameter_substrings(): void
    {
        $info = $this->reflector->methodSignatureInfo(SignatureFixture::class, 'greet');

        self::assertNotNull($info);
        self::assertSame(['string $name', 'int $times'], $info->parameters);
        self::assertStringContainsString('greet(string $name, int $times): string', $info->label);
    }

    public function test_method_signature_info_carries_cleaned_phpdoc(): void
    {
        $info = $this->reflector->methodSignatureInfo(SignatureFixture::class, 'greet');

        self::assertNotNull($info);
        self::assertStringContainsString('Greets a person', $info->documentation);
        self::assertStringNotContainsString('/**', $info->documentation);
        self::assertStringNotContainsString('*/', $info->documentation);
    }

    public function test_method_signature_info_null_for_unknown(): void
    {
        self::assertNull($this->reflector->methodSignatureInfo('\\Nope', 'foo'));
        self::assertNull($this->reflector->methodSignatureInfo('\\DateTimeImmutable', 'noSuchMethod'));
    }

    public function test_class_exists_reflects_known_and_unknown_classes(): void
    {
        self::assertTrue($this->reflector->classExists('\\DateTimeImmutable'));
        self::assertFalse($this->reflector->classExists('\\This\\Does\\Not\\Exist'));
    }

    /**
     * @param list<Completion> $completions
     *
     * @return list<string>
     */
    private function labels(array $completions): array
    {
        return array_map(static fn(Completion $c): string => $c->label, $completions);
    }
}
