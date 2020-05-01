<?php

namespace Phel;

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
     * @var Runtime;
     */
    private static $instance;

    public function __construct(GlobalEnvironment $globalEnv = null) {
        if (is_null($globalEnv)) {
            $globalEnv = new GlobalEnvironment();
        }
        $this->globalEnv = $globalEnv;
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
                return $this->loadFile($file);
            }
        }

        return false;
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

    protected function loadFile($filename) {
        $globalEnv = $this->globalEnv;
        $compiler = new Compiler();
        $compiler->compileFile($filename, $globalEnv);

        return true;
    }

    protected function fileExists($filename): bool {
        return file_exists($filename);
    }
}