<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

use function array_key_exists;
use function array_keys;
use function sprintf;

/**
 * Symfony command loader backed by {@see LazyCommand} wrappers. Each wrapper
 * carries name/aliases/description/hidden metadata up front, so list/help
 * rendering and alias resolution work without constructing a command; the
 * underlying command is built only when it is actually dispatched. The
 * dispatched command is therefore the only one instantiated per invocation.
 */
final readonly class LazyCommandLoader implements CommandLoaderInterface
{
    /** @var array<string, LazyCommand> indexed by canonical name and every alias */
    private array $commandsByName;

    /**
     * @param list<LazyCommand> $commands
     */
    public function __construct(array $commands)
    {
        $index = [];
        foreach ($commands as $command) {
            $index[(string) $command->getName()] = $command;
            foreach ($command->getAliases() as $alias) {
                $index[$alias] = $command;
            }
        }

        $this->commandsByName = $index;
    }

    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        return $this->commandsByName[$name];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->commandsByName);
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_keys($this->commandsByName);
    }
}
