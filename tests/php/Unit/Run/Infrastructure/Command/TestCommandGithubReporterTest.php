<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use Override;
use Phel\Run\Domain\Test\TestCommandOptions;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Run\Infrastructure\Command\TestCommandOptionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

use function getenv;
use function is_string;
use function putenv;

/**
 * On a GitHub Actions runner the `github` reporter joins the default one so
 * failures become inline annotations; an explicit `--reporter` still wins.
 */
final class TestCommandGithubReporterTest extends TestCase
{
    private string|false $previousGithubActions;

    #[Override]
    protected function setUp(): void
    {
        $this->previousGithubActions = getenv('GITHUB_ACTIONS');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv(is_string($this->previousGithubActions)
            ? 'GITHUB_ACTIONS=' . $this->previousGithubActions
            : 'GITHUB_ACTIONS');
    }

    public function test_on_github_actions_the_github_reporter_joins_the_default_one(): void
    {
        putenv('GITHUB_ACTIONS=true');

        self::assertStringContainsString(':reporters [:default :github]', $this->collectAndPrint([]));
    }

    public function test_on_github_actions_the_testdox_shortcut_keeps_its_reporter(): void
    {
        putenv('GITHUB_ACTIONS=true');

        self::assertStringContainsString(':reporters [:testdox :github]', $this->collectAndPrint(['--testdox' => true]));
    }

    public function test_an_explicit_reporter_is_not_extended(): void
    {
        putenv('GITHUB_ACTIONS=true');

        $printed = $this->collectAndPrint(['--reporter' => ['tap']]);

        self::assertStringContainsString(':reporters [:tap]', $printed);
        self::assertStringNotContainsString(':github', $printed);
    }

    public function test_off_github_actions_nothing_is_added(): void
    {
        putenv('GITHUB_ACTIONS');

        self::assertStringNotContainsString(':reporters', $this->collectAndPrint([]));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function collectAndPrint(array $arguments): string
    {
        $command = new TestCommand();
        $input = new ArrayInput($arguments, $command->getDefinition());

        $collected = new TestCommandOptionParser()->collectOptions($input);

        return TestCommandOptions::fromArray($collected)->asPhelHashMap();
    }
}
