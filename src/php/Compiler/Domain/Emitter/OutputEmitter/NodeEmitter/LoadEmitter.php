<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function assert;
use function dirname;

/**
 * Emits the runtime lookup for a `(load ...)` form.
 *
 * The emitted code searches in this order, preferring a pre-compiled
 * sibling so a built artifact runs without needing the source tree:
 *   1. `__DIR__/<loadKey>.php` — a sibling already compiled next to
 *      the caller in a build output.
 *   2. Each `LoadClasspath` entry, preferring a `.php` compiled file
 *      and falling back to the `.phel` source.
 *
 * For classpath-absolute `(load "/foo/bar")` the sibling step is
 * skipped — classpath-absolute loads must resolve against the
 * configured roots.
 */
final class LoadEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LoadNode);

        $resolution = $node->getResolution();

        $this->emitLookupPrelude($resolution->loadKey, $resolution->callerClasspathDir);
        $this->emitSiblingCompiledCheck(!$this->shouldEmitSiblingCheck($resolution->isClasspathAbsolute()));
        $this->emitSrcDirsSearch();
        $this->emitCallerDirFallback($node);
        $this->emitNotFoundGuard();
        $this->emitExecute($node->getCallerNamespace());
    }

    /**
     * The sibling check is only meaningful when the caller was compiled to
     * a file sitting in a build output directory — i.e. FILE mode. CACHE
     * and STATEMENT outputs live in flat cache dirs or are eval'd
     * in-memory, so the `__DIR__/<loadKey>.php` probe is guaranteed to
     * miss and just burns a syscall per `(load ...)`.
     */
    private function shouldEmitSiblingCheck(bool $classpathAbsolute): bool
    {
        if ($classpathAbsolute) {
            return false;
        }

        return $this->outputEmitter->getOptions()->isFileEmitMode();
    }

    /**
     * For REPL / `Phel::run` / build-time statement evaluation we also
     * fall back to the caller's source directory baked at compile time.
     * That directory is captured from the analyzer's source location and
     * lets `(load "sibling")` resolve against the live source tree even
     * when no classpath has been published.
     *
     * Skipped in FILE mode: a build output must stay portable, so it may
     * not bake absolute source-tree paths into runtime lookup code.
     */
    private function emitCallerDirFallback(LoadNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isFileEmitMode()) {
            return;
        }

        $callerFile = $node->getStartSourceLocation()?->getFile();
        if ($callerFile === null || $callerFile === '') {
            return;
        }

        $callerDir = dirname($callerFile);

        $this->outputEmitter->emitLine('if ($__phelLoadPath === null) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('$__phelCallerSrcDir = ');
        $this->outputEmitter->emitLiteral($callerDir);
        $this->outputEmitter->emitLine(';');
        $this->outputEmitter->emitLine('foreach ([$__phelLoadKey, $__phelFullKey ?? $__phelLoadKey] as $__phelAttempt) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelCandidatePhel = $__phelCallerSrcDir . \'/\' . $__phelAttempt . \'.phel\';');
        $this->outputEmitter->emitLine('if (file_exists($__phelCandidatePhel)) { $__phelLoadPath = $__phelCandidatePhel; break; }');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitLookupPrelude(string $loadKey, string $callerClasspathDir): void
    {
        $this->outputEmitter->emitStr('$__phelLoadKey = ');
        $this->outputEmitter->emitLiteral($loadKey);
        $this->outputEmitter->emitLine(';');

        $this->outputEmitter->emitStr('$__phelCallerDir = ');
        $this->outputEmitter->emitLiteral($callerClasspathDir);
        $this->outputEmitter->emitLine(';');

        $this->outputEmitter->emitLine('$__phelLoadPath = null;');
    }

    private function emitSiblingCompiledCheck(bool $skip): void
    {
        if ($skip) {
            return;
        }

        $this->outputEmitter->emitLine('$__phelSibling = __DIR__ . DIRECTORY_SEPARATOR . $__phelLoadKey . \'.php\';');
        $this->outputEmitter->emitLine('if (file_exists($__phelSibling)) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelLoadPath = $__phelSibling;');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitSrcDirsSearch(): void
    {
        $this->outputEmitter->emitLine('if ($__phelLoadPath === null) {');
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('$__phelSrcDirs = \\' . LoadClasspath::class . '::read();');
        // Two src-dir conventions are supported so existing Phel projects
        // keep working:
        //   (a) Classpath-rooted: the src-dir is the classpath root, so
        //       `phel\core` lives at `{src}/phel/core.phel`.
        //   (b) Prefix-rooted: the src-dir already points at the namespace
        //       prefix directory, so `phel\core` lives at `{src}/core.phel`.
        // `$__phelLoadKey` always carries the sibling key (caller-dir-relative);
        // `$__phelFullKey` prepends the caller-namespace dir for convention (a).
        $this->outputEmitter->emitLine('$__phelFullKey = $__phelCallerDir === \'\' ? $__phelLoadKey : $__phelCallerDir . \'/\' . $__phelLoadKey;');
        $this->outputEmitter->emitLine('foreach ($__phelSrcDirs as $__phelSrcDir) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('foreach ([$__phelFullKey, $__phelLoadKey] as $__phelAttempt) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelCandidatePhp = $__phelSrcDir . \'/\' . $__phelAttempt . \'.php\';');
        $this->outputEmitter->emitLine('if (file_exists($__phelCandidatePhp)) { $__phelLoadPath = $__phelCandidatePhp; break 2; }');
        $this->outputEmitter->emitLine('$__phelCandidatePhel = $__phelSrcDir . \'/\' . $__phelAttempt . \'.phel\';');
        $this->outputEmitter->emitLine('if (file_exists($__phelCandidatePhel)) { $__phelLoadPath = $__phelCandidatePhel; break 2; }');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitNotFoundGuard(): void
    {
        $this->outputEmitter->emitLine('if ($__phelLoadPath === null) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine("throw new \\RuntimeException(sprintf('Cannot locate %s for (load ...)', \$__phelLoadKey));");
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitExecute(string $callerNamespace): void
    {
        $this->outputEmitter->emitLine('if (str_ends_with($__phelLoadPath, \'.php\')) {');
        $this->outputEmitter->increaseIndentLevel();
        // Pre-compiled siblings only run `\Phel::addDefinition(...)` calls,
        // so there is no analyzer namespace to track or verify here. This
        // also means a deployed build can require a sibling without needing
        // to bootstrap the compiler runtime.
        $this->outputEmitter->emitLine('/** @psalm-suppress UnresolvableInclude */');
        $this->outputEmitter->emitLine('require_once $__phelLoadPath;');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('} else {');
        $this->outputEmitter->increaseIndentLevel();
        $this->emitPhelSourceLoad($callerNamespace);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitPhelSourceLoad(string $callerNamespace): void
    {
        $this->outputEmitter->emitLine('$__phelPrevNs = \\' . GlobalEnvironmentSingleton::class . '::getInstance()->getNs();');
        $this->outputEmitter->emitLine('\\Phel\\Build\\BuildFacade::enableBuildMode();');
        $this->outputEmitter->emitLine('try {');
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('(new \\Phel\\Build\\BuildFacade())->evalFile($__phelLoadPath);');

        $this->outputEmitter->emitLine('$__phelLoadedNs = \\' . GlobalEnvironmentSingleton::class . '::getInstance()->getNs();');
        $this->outputEmitter->emitStr('if ($__phelLoadedNs !== ');
        $this->outputEmitter->emitLiteral($callerNamespace);
        $this->outputEmitter->emitLine(') {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('throw new \\RuntimeException(sprintf(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine("'File %s must use (in-ns %s) to join the caller namespace, but found (in-ns %s) or (ns ...)',");
        $this->outputEmitter->emitLine('$__phelLoadPath,');
        $this->outputEmitter->emitLiteral($callerNamespace);
        $this->outputEmitter->emitLine(',');
        $this->outputEmitter->emitLine('$__phelLoadedNs');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('));');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('} finally {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('\\Phel\\Build\\BuildFacade::disableBuildMode();');
        $this->outputEmitter->emitLine('\\' . GlobalEnvironmentSingleton::class . '::getInstance()->setNs($__phelPrevNs);');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }
}
