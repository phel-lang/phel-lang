<?php

declare(strict_types=1);

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\FileException;
use Phel\Shared\Parser\ReadModel\CodeSnippet;

/**
 * Backward-compatibility aliases for classes relocated to `Phel\Shared` in 0.37.
 *
 * Cached compiled PHP from older Phel installations and user projects whose
 * `.phel/cache/` artefacts pre-date the relocation still reference the old
 * FQNs. Each `class_alias` call here keeps those references resolvable for
 * one release while consumers regenerate their caches.
 *
 * Registered via Composer `autoload.files`; runs on every request before
 * any other Phel code touches these classes.
 *
 * Remove after 0.38: every consumer should have refreshed its compile cache
 * by then.
 *
 * The legacy FQNs are stitched together with string concatenation so static
 * analysers (rector, phpstan) cannot rewrite them into `::class` constants
 * for classes that no longer exist at those locations.
 */
$oldNs = 'Phel\Compiler\Domain\\';

class_alias(CompilerException::class, $oldNs . 'Exceptions\\CompilerException');
class_alias(AbstractLocatedException::class, $oldNs . 'Exceptions\\AbstractLocatedException');
class_alias(ErrorCode::class, $oldNs . 'Exceptions\\ErrorCode');
class_alias(FileException::class, $oldNs . 'Evaluator\\Exceptions\\FileException');
class_alias(CompiledCodeIsMalformedException::class, $oldNs . 'Evaluator\\Exceptions\\CompiledCodeIsMalformedException');
class_alias(CodeSnippet::class, $oldNs . 'Parser\\ReadModel\\CodeSnippet');
