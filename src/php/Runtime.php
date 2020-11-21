<?php

declare(strict_types=1);

namespace Phel;

use Phel\Compiler\FileCompiler;
use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\GlobalEnvironmentInterface;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\HtmlExceptionPrinter;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use RuntimeException;
use Throwable;

class Runtime implements RuntimeInterface
{
    protected GlobalEnvironmentInterface $globalEnv;

    private static ?RuntimeInterface $instance = null;

    /** @var string[] */
    private array $loadedNs = [];

    private array $paths = [];

    private ?string $cacheDirectory = null;

    private function __construct(
        GlobalEnvironmentInterface $globalEnv,
        ?string $cacheDirectory = null
    ) {
        set_exception_handler([$this, 'exceptionHandler']);

        $this->globalEnv = $globalEnv;
        $this->cacheDirectory = $cacheDirectory;
        $this->addPath('phel\\', [__DIR__ . '/../phel']);
    }

    public function getEnv(): GlobalEnvironmentInterface
    {
        return $this->globalEnv;
    }

    public function addPath(string $namespacePrefix, array $path): void
    {
        $length = strlen($namespacePrefix);
        if ('\\' !== $namespacePrefix[$length - 1]) {
            throw new \InvalidArgumentException('A non-empty prefix must end with a namespace separator.');
        }

        if (isset($this->paths[$namespacePrefix])) {
            $this->paths[$namespacePrefix] = [...$this->paths[$namespacePrefix], ...$path];
        } else {
            $this->paths[$namespacePrefix] = $path;
        }
    }

    public static function initialize(
        ?GlobalEnvironmentInterface $globalEnv = null,
        ?string $cacheDirectory = null
    ): self {
        if (self::$instance !== null) {
            throw new RuntimeException('Runtime is already initialized');
        }

        self::$instance = new self(
            $globalEnv ?? new GlobalEnvironment(),
            $cacheDirectory
        );

        return self::$instance;
    }

    /**
     * @interal
     */
    public static function newInstance(GlobalEnvironmentInterface $globalEnv, string $cacheDirectory = null): self
    {
        return new self($globalEnv, $cacheDirectory);
    }

    /**
     * @interal
     */
    public static function initializeNew(GlobalEnvironmentInterface $globalEnv, string $cacheDirectory = null): self
    {
        self::$instance = new self($globalEnv, $cacheDirectory);

        return self::$instance;
    }

    public static function getInstance(): RuntimeInterface
    {
        if (null === self::$instance) {
            throw new RuntimeException('Runtime must first be initialized. Call Runtime::initialize()');
        }

        return self::$instance;
    }

    public function exceptionHandler(Throwable $exception): void
    {
        if (PHP_SAPI === 'cli') {
            $printer = TextExceptionPrinter::readableWithStyle();
        } else {
            $printer = HtmlExceptionPrinter::create();
        }

        if ($exception instanceof CompilerException) {
            $printer->printException($exception->getNestedException(), $exception->getCodeSnippet());
        } else {
            $printer->printStackTrace($exception);
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
            throw new \InvalidArgumentException("Can not load cached file: {$filename}");
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
        $globalEnv = $this->globalEnv;
        $compiler = new FileCompiler($globalEnv);
        $code = $compiler->compile($filename);

        $cacheFilePath = $this->getCachedFilePath($filename, $ns);
        if ($cacheFilePath) {
            file_put_contents(
                $cacheFilePath,
                "<?php\n\n" . $code
            );
        }
    }
}
