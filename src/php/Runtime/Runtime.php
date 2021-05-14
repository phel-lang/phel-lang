<?php

declare(strict_types=1);

namespace Phel\Runtime;

use InvalidArgumentException;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\MetaInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use Throwable;

class Runtime implements RuntimeInterface
{
    protected GlobalEnvironmentInterface $globalEnv;
    private ExceptionPrinterInterface $exceptionPrinter;
    private CompilerFacadeInterface $compilerFacade;
    private ?string $cacheDirectory = null;

    /** @var string[] */
    private array $loadedNs = [];

    /** @var array<string, array<int, string>> */
    private array $paths = [];

    public function __construct(
        GlobalEnvironmentInterface $globalEnv,
        ExceptionPrinterInterface $exceptionPrinter,
        CompilerFacadeInterface $compilerFacade,
        ?string $cacheDirectory = null
    ) {
        set_exception_handler([$this, 'exceptionHandler']);
        $this->addPath('phel\\', [__DIR__ . '/../../phel']);

        $this->globalEnv = $globalEnv;
        $this->exceptionPrinter = $exceptionPrinter;
        $this->compilerFacade = $compilerFacade;
        $this->cacheDirectory = $cacheDirectory;
    }

    public function getEnv(): GlobalEnvironmentInterface
    {
        return $this->globalEnv;
    }

    /**
     * @param string $namespacePrefix
     * @param array<int, string> $path
     */
    public function addPath(string $namespacePrefix, array $path): void
    {
        $length = strlen($namespacePrefix);
        if ('\\' !== $namespacePrefix[$length - 1]) {
            throw new InvalidArgumentException('A non-empty prefix must end with a namespace separator.');
        }

        if (isset($this->paths[$namespacePrefix])) {
            $this->paths[$namespacePrefix] = [...$this->paths[$namespacePrefix], ...$path];
        } else {
            $this->paths[$namespacePrefix] = $path;
        }
    }

    public function exceptionHandler(Throwable $exception): void
    {
        if ($exception instanceof CompilerException) {
            $this->exceptionPrinter->printException($exception->getNestedException(), $exception->getCodeSnippet());
        } else {
            $this->exceptionPrinter->printStackTrace($exception);
        }
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function loadNs(string $ns): bool
    {
        if (in_array($ns, $this->loadedNs, true)) {
            return false;
        }

        $file = $this->findFile($ns);

        if (!$file) {
            return false;
        }

        $this->loadedNs[] = $ns;

        if ($this->isCached($file, $ns)) {
            return $this->loadCachedFile($file, $ns);
        }

        $this->loadFile($file, $ns);

        return true;
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function loadFileIntoNamespace(string $ns, string $file): void
    {
        $this->loadFile($file, $ns);
    }

    private function findFile(string $ns): ?string
    {
        $nsPath = str_replace('\\', DIRECTORY_SEPARATOR, $ns);

        $subPath = $ns;
        while (false !== $lastPos = strrpos($subPath, '\\')) {
            $subPath = substr($subPath, 0, $lastPos);
            $search = $subPath . '\\';

            if (isset($this->paths[$search])) {
                $pathEnd = DIRECTORY_SEPARATOR . substr($nsPath, $lastPos + 1) . '.phel';
                foreach ($this->paths[$search] as $dir) {
                    $file = $dir . $pathEnd;
                    if ($this->fileExists($file)) {
                        return $file;
                    }
                }
            }
        }

        return null;
    }

    protected function fileExists(string $filename): bool
    {
        return file_exists($filename);
    }

    private function isCached(string $file, string $ns): bool
    {
        $filename = $this->getCachedFilePath($file, $ns);

        return $filename && file_exists($filename);
    }

    private function getCachedFilePath(string $file, string $ns): ?string
    {
        if ($this->cacheDirectory) {
            return $this->cacheDirectory
                . DIRECTORY_SEPARATOR
                . str_replace('\\', '.', $ns)
                . '.' . md5_file($file)
                . '.php';
        }

        return null;
    }

    private function loadCachedFile(string $filename, string $ns): bool
    {
        // Require cache file
        $path = $this->getCachedFilePath($filename, $ns);

        if (!$path) {
            throw new InvalidArgumentException("Can not load cached file: {$filename}");
        }

        // Update global environment
        if (isset($GLOBALS['__phel'][$ns])) {
            /** @var array<string, MetaInterface> $nsDefinitions */
            $nsDefinitions = $GLOBALS['__phel'][$ns];
            /** @var string $name */
            foreach (array_keys($nsDefinitions) as $name) {
                /** @var PersistentMapInterface<mixed, mixed> $meta */
                $meta = $GLOBALS['__phel_meta'][$ns][$name] ?? TypeFactory::getInstance()->emptyPersistentMap();
                /** @var MetaInterface $def */
                $def = $nsDefinitions[$name];
                if ($meta[new Keyword('private')] !== true) {
                    $defMeta = $def->getMeta();
                    $this->globalEnv->addDefinition(
                        $ns,
                        Symbol::create($name),
                        $defMeta ? $defMeta : TypeFactory::getInstance()->emptyPersistentMap()
                    );
                }
            }
        }

        return true;
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    protected function loadFile(string $filename, string $ns): void
    {
        $code = $this->compilerFacade->compile($filename);

        $cacheFilePath = $this->getCachedFilePath($filename, $ns);
        if ($cacheFilePath) {
            file_put_contents(
                $cacheFilePath,
                "<?php\n\n" . $code
            );
        }
    }
}
