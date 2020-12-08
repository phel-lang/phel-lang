<?php

declare(strict_types=1);

namespace Phel\Runtime;

use InvalidArgumentException;
use Phel\Compiler\CompilerFactoryInterface;
use Phel\Compiler\GlobalEnvironmentInterface;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ExceptionPrinterInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Throwable;

class Runtime implements RuntimeInterface
{
    protected GlobalEnvironmentInterface $globalEnv;

    private ExceptionPrinterInterface $exceptionPrinter;

    private CompilerFactoryInterface $compilerFactory;

    private ?string $cacheDirectory = null;

    /** @var string[] */
    private array $loadedNs = [];

    private array $paths = [];

    public function __construct(
        GlobalEnvironmentInterface $globalEnv,
        ExceptionPrinterInterface $exceptionPrinter,
        CompilerFactoryInterface $compilerFactory,
        ?string $cacheDirectory = null
    ) {
        set_exception_handler([$this, 'exceptionHandler']);
        $this->addPath('phel\\', [__DIR__ . '/../phel']);

        $this->globalEnv = $globalEnv;
        $this->exceptionPrinter = $exceptionPrinter;
        $this->compilerFactory = $compilerFactory;
        $this->cacheDirectory = $cacheDirectory;
    }

    public function getEnv(): GlobalEnvironmentInterface
    {
        return $this->globalEnv;
    }

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
            foreach (array_keys($GLOBALS['__phel'][$ns]) as $name) {
                /** @var Table $meta */
                $meta = $GLOBALS['__phel_meta'][$ns][$name] ?? new Table();
                if ($meta[new Keyword('private')] !== true) {
                    $this->globalEnv->addDefinition(
                        $ns,
                        Symbol::create($name),
                        $GLOBALS['__phel'][$ns][$name]->getMeta()
                    );
                }
            }
        }

        return true;
    }

    protected function loadFile(string $filename, string $ns): void
    {
        $code = $this->compilerFactory
            ->createFileCompiler($this->globalEnv)
            ->compile($filename);

        $cacheFilePath = $this->getCachedFilePath($filename, $ns);
        if ($cacheFilePath) {
            file_put_contents(
                $cacheFilePath,
                "<?php\n\n" . $code
            );
        }
    }
}
