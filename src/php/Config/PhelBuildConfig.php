<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;
use Phel\Shared\ScalarCoercion;

use function count;
use function sprintf;

/**
 * Immutable build configuration nested under {@see PhelConfig} (`out` key).
 *
 * Canonical API: the `with*()` setters.
 */
final readonly class PhelBuildConfig implements JsonSerializable
{
    public const string DEST_DIR = 'dir';

    public const string MAIN_PHEL_NAMESPACE = 'main-phel-namespace';

    public const string MAIN_PHP_FILENAME = 'main-php-filename';

    public const string MAIN_PHP_PATH = 'main-php-path';

    private const string DEFAULT_DEST_DIR = 'out';

    private const string DEFAULT_PHP_FILENAME = 'index.php';

    public string $mainPhpPath;

    public function __construct(
        public string $mainPhelNamespace = '',
        string $mainPhpPath = '',
        public string $destDir = '',
    ) {
        $this->mainPhpPath = $mainPhpPath === ''
            ? ''
            : $this->normalizePhpExtension($mainPhpPath);
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            mainPhelNamespace: ScalarCoercion::toString($array[self::MAIN_PHEL_NAMESPACE] ?? null),
            mainPhpPath: ScalarCoercion::toString($array[self::MAIN_PHP_PATH] ?? null),
            destDir: ScalarCoercion::toString($array[self::DEST_DIR] ?? null),
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            self::MAIN_PHEL_NAMESPACE => $this->mainPhelNamespace,
            self::DEST_DIR => $this->getDestDir(),
            self::MAIN_PHP_FILENAME => $this->getPhpFilename(),
            self::MAIN_PHP_PATH => $this->getMainPhpPath(),
        ];
    }

    public function withMainPhelNamespace(string $namespace): self
    {
        return new self($namespace, $this->mainPhpPath, $this->destDir);
    }

    public function getMainPhelNamespace(): string
    {
        return $this->mainPhelNamespace;
    }

    public function withMainPhpPath(string $path): self
    {
        return new self($this->mainPhelNamespace, $path, $this->destDir);
    }

    public function getMainPhpPath(): string
    {
        if (str_contains($this->mainPhpPath, '/')) {
            return $this->mainPhpPath;
        }

        return sprintf(
            '%s/%s',
            $this->getDestDir(),
            $this->getPhpFilename(),
        );
    }

    public function withDestDir(string $dir): self
    {
        return new self($this->mainPhelNamespace, $this->mainPhpPath, $dir);
    }

    public function shouldCreateEntryPointPhpFile(): bool
    {
        return (bool) $this->mainPhelNamespace;
    }

    private function getDestDir(): string
    {
        if ($this->destDir !== '') {
            return $this->destDir;
        }

        if ($this->mainPhpPath !== '') {
            $explode = explode('/', $this->mainPhpPath);
            if (count($explode) !== 1) {
                array_pop($explode);
                return implode('/', $explode);
            }
        }

        return self::DEFAULT_DEST_DIR;
    }

    private function getPhpFilename(): string
    {
        if ($this->mainPhpPath !== '') {
            $explode = explode('/', $this->mainPhpPath);
            if (count($explode) === 1) {
                return $explode[0];
            }

            return array_pop($explode);
        }

        return self::DEFAULT_PHP_FILENAME;
    }

    private function normalizePhpExtension(string $string): string
    {
        $suffix = '.php';
        if (str_ends_with($string, $suffix)) {
            return $string;
        }

        return $string . $suffix;
    }
}
