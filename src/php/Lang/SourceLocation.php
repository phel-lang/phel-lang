<?php

declare(strict_types=1);

namespace Phel\Lang;

final readonly class SourceLocation
{
    /**
     * @param ?self $expansionOrigin where the form was actually written, set only when
     *                               this location was stamped onto a form the user never
     *                               typed there (a macro or inline expansion pasted at a
     *                               call site). `null` means the location is the form's
     *                               own position; {@see self::unknown()} means the form
     *                               came from an expansion whose origin is not known.
     */
    public function __construct(
        private string $file,
        private int $line,
        private int $column,
        private ?self $expansionOrigin = null,
    ) {}

    /**
     * A location naming no file. Used as the expansion origin of a macro whose
     * definition carries no `:start-location`, so consumers can still tell
     * "produced by an expansion" apart from "written here".
     */
    public static function unknown(): self
    {
        return new self('', 0, 0);
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * Where the macro/inline definition that produced this form was written,
     * or `null` when the form was written at this very location.
     */
    public function getExpansionOrigin(): ?self
    {
        return $this->expansionOrigin;
    }

    public function withExpansionOrigin(?self $expansionOrigin): self
    {
        return new self($this->file, $this->line, $this->column, $expansionOrigin);
    }
}
