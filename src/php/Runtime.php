<?php

namespace Phel;

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
     * @var Runtime;
     */
    private static $instance;

    public function __construct(GlobalEnvironment $globalEnv = null, $cacheDiretory = null) {
        if (is_null($globalEnv)) {
            $globalEnv = new GlobalEnvironment();
        }
        $this->globalEnv = $globalEnv;
        $this->cacheDiretory = $cacheDiretory;
        $this->addPath('phel\\', [__DIR__ . '/../phel']);
    }

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new Runtime();
        }
        return self::$instance;
    }

    public function addPath(string $namespacePrefix, array $path) {
        $length = strlen($namespacePrefix);
        if ('\\' !== $namespacePrefix[$length - 1]) {
            throw new \InvalidArgumentException("A non-empty prefix must end with a namespace separator.");
        }
        $this->paths[$namespacePrefix] = $path;
    }

    public function loadNs($ns) {
        if (!in_array($ns, $this->loadedNs)) {

            $file = $this->findFile($ns);
            if ($file) {
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

    protected function isCached($file, $ns) {
        if ($this->cacheDiretory) {
            return file_exists($this->getCachedFilePath($file, $ns));
        } else {
            return false;
        }
    }

    protected function getCachedFilePath($file, $ns) {
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

    protected function findFile($ns) {
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

    protected function loadFile($filename, $ns) {
        $globalEnv = $this->globalEnv;
        $compiler = new Compiler();
        $code = $compiler->compileFile($filename, $globalEnv);

        if ($this->cacheDiretory) {
            file_put_contents(
                $this->getCachedFilePath($filename, $ns), 
                "<?php\n\n" . $code
            );
        }

        return true;
    }

    protected function loadCachedFile($filename, $ns) {
        // Require cache file
        require $this->getCachedFilePath($filename, $ns);

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

    protected function fileExists($filename): bool {
        return file_exists($filename);
    }
}