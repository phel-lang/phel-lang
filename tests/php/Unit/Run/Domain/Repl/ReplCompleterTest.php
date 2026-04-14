<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel;
use Phel\Api\Application\ReplCompleter;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Transfer\CompletionResultTransfer;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Lang\FnInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ReplCompleterTest extends TestCase
{
    private ReplCompleter $completer;

    public static function tearDownAfterClass(): void
    {
        Phel::clear();
    }

    protected function setUp(): void
    {
        Phel::clear();

        $phelFnLoader = self::createStub(PhelFnLoaderInterface::class);
        $this->completer = new ReplCompleter($phelFnLoader);
    }

    public function test_empty_input_returns_nothing(): void
    {
        self::assertSame([], $this->completer->complete(''));
    }

    public function test_phel_function_completion(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\core', 'myfn', $fn);

        self::assertSame(['myfn'], $this->completer->complete('my'));
    }

    public function test_php_function_completion(): void
    {
        $matches = $this->completer->complete('php/strl');

        self::assertContains('php/strlen', $matches);
    }

    public function test_php_class_completion(): void
    {
        $matches = $this->completer->complete('php/DateT');

        self::assertContains('php/DateTime', $matches);
    }

    public function test_alias_based_completion(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\html', 'html', $fn);
        Phel::addDefinition('phel\\html', 'div', $fn);

        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('user');
        $globalEnv->addRequireAlias(
            'user',
            Symbol::create('h'),
            Symbol::create('phel\\html'),
        );

        $phelFnLoader = self::createStub(PhelFnLoaderInterface::class);
        $completer = new ReplCompleter($phelFnLoader, [], $globalEnv);

        $matches = $completer->complete('h/ht');

        self::assertContains('h/html', $matches);
        self::assertNotContains('h/div', $matches);
    }

    public function test_referred_symbol_completion(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\html', 'html', $fn);

        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('user');
        $globalEnv->addRefer(
            'user',
            Symbol::create('html'),
            Symbol::create('phel\\html'),
        );

        $phelFnLoader = self::createStub(PhelFnLoaderInterface::class);
        $completer = new ReplCompleter($phelFnLoader, [], $globalEnv);

        $matches = $completer->complete('htm');

        self::assertContains('html', $matches);
    }

    public function test_empty_input_returns_nothing_with_types(): void
    {
        self::assertSame([], $this->completer->completeWithTypes(''));
    }

    public function test_function_has_function_type(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\core', 'myfn', $fn);

        $results = $this->completer->completeWithTypes('my');

        self::assertCount(1, $results);
        self::assertInstanceOf(CompletionResultTransfer::class, $results[0]);
        self::assertSame('myfn', $results[0]->candidate);
        self::assertSame('function', $results[0]->type);
    }

    public function test_macro_has_macro_type(): void
    {
        $fn = self::createStub(FnInterface::class);
        $meta = Phel::map(Keyword::create('macro'), true);
        Phel::addDefinition('phel\\core', 'my-macro', $fn, $meta);

        $results = $this->completer->completeWithTypes('my-mac');

        self::assertCount(1, $results);
        self::assertSame('my-macro', $results[0]->candidate);
        self::assertSame('macro', $results[0]->type);
    }

    public function test_var_has_var_type(): void
    {
        Phel::addDefinition('phel\\core', 'my-var', 42);

        $results = $this->completer->completeWithTypes('my-va');

        self::assertCount(1, $results);
        self::assertSame('my-var', $results[0]->candidate);
        self::assertSame('var', $results[0]->type);
    }

    public function test_keyword_has_keyword_type(): void
    {
        Phel::addDefinition('phel\\core', 'my-kw', Keyword::create('test'));

        $results = $this->completer->completeWithTypes('my-kw');

        self::assertCount(1, $results);
        self::assertSame('my-kw', $results[0]->candidate);
        self::assertSame('keyword', $results[0]->type);
    }

    public function test_php_function_has_php_function_type(): void
    {
        $results = $this->completer->completeWithTypes('php/strl');

        $strlen = null;
        foreach ($results as $result) {
            if ($result->candidate === 'php/strlen') {
                $strlen = $result;
                break;
            }
        }

        self::assertNotNull($strlen);
        self::assertSame('php-function', $strlen->type);
    }

    public function test_php_class_has_class_type(): void
    {
        $results = $this->completer->completeWithTypes('php/DateT');

        $dateTime = null;
        foreach ($results as $result) {
            if ($result->candidate === 'php/DateTime') {
                $dateTime = $result;
                break;
            }
        }

        self::assertNotNull($dateTime);
        self::assertSame('class', $dateTime->type);
    }

    public function test_alias_completion_has_type(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\html', 'html', $fn);

        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('user');
        $globalEnv->addRequireAlias(
            'user',
            Symbol::create('h'),
            Symbol::create('phel\\html'),
        );

        $phelFnLoader = self::createStub(PhelFnLoaderInterface::class);
        $completer = new ReplCompleter($phelFnLoader, [], $globalEnv);

        $results = $completer->completeWithTypes('h/ht');

        self::assertCount(1, $results);
        self::assertSame('h/html', $results[0]->candidate);
        self::assertSame('function', $results[0]->type);
    }

    public function test_referred_symbol_has_type(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\html', 'html', $fn);

        $globalEnv = new GlobalEnvironment();
        $globalEnv->setNs('user');
        $globalEnv->addRefer(
            'user',
            Symbol::create('html'),
            Symbol::create('phel\\html'),
        );

        $phelFnLoader = self::createStub(PhelFnLoaderInterface::class);
        $completer = new ReplCompleter($phelFnLoader, [], $globalEnv);

        $results = $completer->completeWithTypes('htm');

        self::assertCount(1, $results);
        self::assertSame('html', $results[0]->candidate);
        self::assertSame('function', $results[0]->type);
    }

    public function test_complete_still_returns_strings(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\core', 'myfn', $fn);

        $results = $this->completer->complete('my');

        self::assertSame(['myfn'], $results);
        self::assertContainsOnly('string', $results);
    }

    public function test_completion_result_transfer_to_array(): void
    {
        $result = new CompletionResultTransfer('map', 'function');

        self::assertSame([
            'candidate' => 'map',
            'type' => 'function',
        ], $result->toArray());
    }

    public function test_qualified_function_in_non_core_namespace(): void
    {
        $fn = self::createStub(FnInterface::class);
        Phel::addDefinition('phel\\string', 'join', $fn);

        $results = $this->completer->completeWithTypes('phel\\string\\jo');

        self::assertCount(1, $results);
        self::assertSame('phel\\string\\join', $results[0]->candidate);
        self::assertSame('function', $results[0]->type);
    }
}
