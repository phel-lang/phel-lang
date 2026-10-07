<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\LoadClasspath;

use function assert;
use function dirname;
use function str_repeat;
use function substr_count;

/**
 * Emits the runtime lookup for a `(load ...)` form.
 *
 * The emitted code searches in this order, preferring a pre-compiled
 * file so a built artifact runs without needing the source tree:
 *   1. In a build output, for a classpath-absolute `(load "/foo/bar")`
 *      only: `<output root>/foo/bar.php`. The output mirrors the
 *      namespaces, so the root is as many levels above the caller as its
 *      namespace has directories.
 *   2. In a build output: `__DIR__/<loadKey>.php`, a file compiled next to
 *      the caller. That is where a build puts a file from the caller's
 *      source dir, also for an absolute load when the source dir holds the
 *      namespace prefix, as in the flat layout `phel init` creates. For an
 *      absolute load it is probed first and step 1 overrides it.
 *   3. Each `LoadClasspath` entry, preferring a `.php` compiled file
 *      and falling back to the `.phel` source.
 *
 * @internal
 */
final class LoadEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LoadNode);

        $resolution = $node->getResolution();

        $this->emitLookupPrelude($resolution->loadKey, $resolution->callerClasspathDir);
        if ($this->isFileEmitMode()) {
            $this->emitSiblingCompiledCheck();
            if ($resolution->isClasspathAbsolute()) {
                $this->emitOutputRootCompiledCheck($node->getCallerNamespace());
            }
        }

        $this->emitSrcDirsSearch();
        $this->emitCallerDirFallback($node);
        $this->emitNotFoundGuard($node);
        $this->emitExecute($node->getCallerNamespace());
    }

    /**
     * The compiled-file checks are only meaningful when the caller was
     * compiled to a file sitting in a build output directory, i.e. FILE
     * mode. CACHE and STATEMENT outputs live in flat cache dirs or are
     * eval'd in memory, so the probes are guaranteed to miss and just burn
     * a syscall per `(load ...)`.
     */
    private function isFileEmitMode(): bool
    {
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
        if ($this->isFileEmitMode()) {
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

        // While `phel build` is producing a deployable artifact, `(load ...)`
        // must resolve to `.phel` and recompile so the secondary is cached and
        // harvested into the output tree. Otherwise a precompiled `.php`
        // (e.g. a stdlib sibling shipped in the PHAR) would be required
        // directly and the build would emit no compiled file for it. Inert at
        // deploy time, where build mode is off.
        $this->outputEmitter->emitLine('$__phelBuildMode = \\Phel\\Lang\\Registry::getInstance()->getDefinition(\'phel.core\', \'*build-mode*\') === true;');
    }

    /**
     * Emitted after the sibling check and wins over it, as `phel run` would
     * pick `<src>/foo/bar.phel` over a same-named file next to the caller.
     */
    private function emitOutputRootCompiledCheck(string $callerNamespace): void
    {
        $depth = substr_count($this->outputEmitter->mungeEncodePhpNs($callerNamespace), '\\');

        $this->outputEmitter->emitStr('$__phelRootCompiled = __DIR__ . ');
        $this->outputEmitter->emitLiteral(str_repeat('/..', $depth) . '/');
        $this->outputEmitter->emitLine(' . $__phelLoadKey . \'.php\';');
        $this->outputEmitter->emitLine('if (!$__phelBuildMode && file_exists($__phelRootCompiled)) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$__phelLoadPath = $__phelRootCompiled;');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitSiblingCompiledCheck(): void
    {
        $this->outputEmitter->emitLine('$__phelSibling = __DIR__ . DIRECTORY_SEPARATOR . $__phelLoadKey . \'.php\';');
        $this->outputEmitter->emitLine('if (!$__phelBuildMode && file_exists($__phelSibling)) {');
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
        $this->outputEmitter->emitLine('if (!$__phelBuildMode && file_exists($__phelCandidatePhp)) { $__phelLoadPath = $__phelCandidatePhp; break 2; }');
        $this->outputEmitter->emitLine('$__phelCandidatePhel = $__phelSrcDir . \'/\' . $__phelAttempt . \'.phel\';');
        $this->outputEmitter->emitLine('if (file_exists($__phelCandidatePhel)) { $__phelLoadPath = $__phelCandidatePhel; break 2; }');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }

    private function emitNotFoundGuard(LoadNode $node): void
    {
        $this->outputEmitter->emitLine('if ($__phelLoadPath === null) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine(
            "throw new \\RuntimeException(sprintf('Cannot locate %s for (load ...)', \$__phelLoadKey));",
            $node->getStartSourceLocation(),
        );
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
        $this->outputEmitter->emitLine('try {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('\\Phel\\Run\\Runtime\\PhelSourceLoader::load($__phelLoadPath);');
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
        $this->outputEmitter->emitLine('\\' . GlobalEnvironmentSingleton::class . '::getInstance()->setNs($__phelPrevNs);');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');
    }
}
