<?php

declare(strict_types=1);

namespace Phel\Command\Test;

final class TestCommandOptions
{
    private const FILTER_OPTION = '--filter=';

    private string $filter = '';

    public static function empty(): self
    {
        return new self();
    }

    public static function fromArray(array $options): self
    {
        $self = new self();

        foreach ($options as $option) {
            if (false !== strpos($option, self::FILTER_OPTION)) {
                $self->filter = str_replace('-', '_', substr($option, strlen(self::FILTER_OPTION)));
            }
        }

        return $self;
    }

    public function asPhelHashMap(): string
    {
        return sprintf(
            '{:filter %s}',
            $this->filter ? "\"{$this->filter}\"" : 'nil'
        );
    }
}
