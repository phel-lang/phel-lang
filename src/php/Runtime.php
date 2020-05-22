<?php

namespace Phel;

use Exception;
use Phel\Exceptions\HtmlExceptionPrinter;
use Phel\Exceptions\TextExceptionPrinter;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

class Runtime {

    /**
     * @var GlobalEnvironment
     */
    private $globalEnv;

    /**
     * @var string[]
     */
    private $loadedNs = [];

    /**
     * @var array
     */
    private $paths = [];

    /**
     * @var string|null
     */
    private $cacheDiretory;

    /**
     * @var Runtime|null
     */
    private static $instance;

    public function __construct(GlobalEnvironment $globalEnv = null, string $cacheDiretory = null) {
        set_exception_handler(array($this, 'exceptionHandler'));

        if (is_null($globalEnv)) {
            $globalEnv = new GlobalEnvironment();
        }
        $this->globalEnv = $globalEnv;
        $this->cacheDiretory = $cacheDiretory;
        $this->addPath('phel\\', [__DIR__ . '/../phel']);
    }

    /**
     * @param Exception $exception
     */
    public function exceptionHandler($exception) {
        if (php_sapi_name() == 'cli') {
            $printer = new TextExceptionPrinter();
        } else {
            $printer = new HtmlExceptionPrinter();
        }

        echo $printer->printStackTrace($exception);
        /*var_dump($exception->getTrace());*/
        echo $exception->__toString();
    }

    public static function getInstance(): Runtime {
        if (is_null(self::$instance)) {
            self::$instance = new Runtime();
        }
        return self::$instance;
    }

    public function addPath(string $namespacePrefix, array $path): void {
        $length = strlen($namespacePrefix);
        if ('\\' !== $namespacePrefix[$length - 1]) {
            throw new \InvalidArgumentException("A non-empty prefix must end with a namespace separator.");
        }
        $this->paths[$namespacePrefix] = $path;
    }

    public function loadNs(string $ns): bool {
        if (!in_array($ns, $this->loadedNs)) {

            $file = $this->findFile($ns);
            if (is_string($file)) {
                $this->loadedNs[] = $ns;

                if ($this->isCached($file, $ns)) {
                    return $this->loadCachedFile($file, $ns);
                } else {    
                    return $this->loadFile($file, $ns);
                }
            }
        }

        return false;
    }

    protected function isCached(string $file, string $ns): bool {
        $filename = $this->getCachedFilePath($file, $ns);

        return $filename && file_exists($filename);
    }

    protected function getCachedFilePath(string $file, string $ns): ?string {
        if ($this->cacheDiretory) {
            return $this->cacheDiretory 
                . DIRECTORY_SEPARATOR
                . str_replace('\\', '.', $ns) 
                . '.' . md5_file($file)
                . '.php';
        } else {
            return null;
        }
    }

    /**
     * @return string|bool
     */
    protected function findFile(string $ns) {
        $nsPath = strtr($ns, '\\', DIRECTORY_SEPARATOR);

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

        return false;
    }

    protected function loadFile(string $filename, string $ns): bool {
        $globalEnv = $this->globalEnv;
        $compiler = new Compiler();
        $code = $compiler->compileFile($filename, $globalEnv);

        $cacheFilePath = $this->getCachedFilePath($filename, $ns);
        if ($cacheFilePath) {
            file_put_contents(
                $cacheFilePath, 
                "<?php\n\n" . $code
            );
        }

        return true;
    }

    protected function loadCachedFile(string $filename, string $ns): bool {
        // Require cache file
        $path = $this->getCachedFilePath($filename, $ns);

        if (!$path) {
            throw new \InvalidArgumentException("Can not load cached file: " . $filename);
        }

        // Update global environment
        if (isset($GLOBALS['__phel'][$ns])) {
            foreach (array_keys($GLOBALS['__phel'][$ns]) as $name) {
                /** @var Table $meta */
                $meta = $GLOBALS['__phel'][$ns][$name]->getMeta();
                if ($meta[new Keyword('private')] !== true) {
                    $this->globalEnv->addDefintion(
                        $ns, 
                        new Symbol($name),
                        $GLOBALS['__phel'][$ns][$name]->getMeta()
                    );
                }
            }
        }

        return true;
    }

    protected function fileExists(string $filename): bool {
        return file_exists($filename);
    }
}