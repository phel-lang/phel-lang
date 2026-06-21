<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Shared\NamespaceInformation;

use function count;
use function is_array;
use function is_int;
use function is_string;

/**
 * A single persisted directory-scan result: the grouped + topologically sorted
 * namespace list for one resolved directory set, together with the validation
 * fingerprint used to decide whether the on-disk index may be served without
 * re-walking the tree.
 *
 * Validation combines two independent layers so that a stale namespace can
 * never be served:
 *
 * 1. Per-directory `mtime` + phel-file `fileCount`. A directory's mtime does
 *    NOT change on an in-place edit, only on add/remove/rename — and mtime has
 *    1s resolution — so the file count is paired with it to catch same-second
 *    add/remove churn that the mtime alone would miss.
 * 2. Per-file `mtime` (authoritative). Each scanned file's mtime is stored and
 *    re-checked, which is what guards each namespace's name + dependency list
 *    against in-place edits.
 */
final readonly class ScanIndexEntry
{
    /**
     * @param array<string, array{mtime: int, fileCount: int}> $perDir resolved dir => fingerprint
     * @param list<array{file: string, mtime: int}>            $files  scanned file => mtime, ordered
     * @param list<NamespaceInformation>                       $infos  grouped + sorted result
     */
    public function __construct(
        public array $perDir,
        public array $files,
        public array $infos,
    ) {}

    /**
     * @param callable(string): int $fileCountOf returns the current phel-file count for a resolved dir
     */
    public function isValid(callable $fileCountOf): bool
    {
        foreach ($this->perDir as $dir => $fingerprint) {
            if (!is_dir($dir)) {
                return false;
            }

            $currentMtime = @filemtime($dir);
            if ($currentMtime === false || $currentMtime !== $fingerprint['mtime']) {
                return false;
            }

            if ($fileCountOf($dir) !== $fingerprint['fileCount']) {
                return false;
            }
        }

        foreach ($this->files as $file) {
            if (!file_exists($file['file'])) {
                return false;
            }

            $currentMtime = @filemtime($file['file']);
            if ($currentMtime === false || $currentMtime !== $file['mtime']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     perDir: array<string, array{mtime: int, fileCount: int}>,
     *     files: list<array{file: string, mtime: int}>,
     *     infos: list<array{file: string, namespace: string, dependencies: list<string>, isPrimaryDefinition: bool}>
     * }
     */
    public function toArray(): array
    {
        $infos = [];
        foreach ($this->infos as $info) {
            $infos[] = [
                'file' => $info->getFile(),
                'namespace' => $info->getNamespace(),
                'dependencies' => $info->getDependencies(),
                'isPrimaryDefinition' => $info->isPrimaryDefinition(),
            ];
        }

        return [
            'perDir' => $this->perDir,
            'files' => $this->files,
            'infos' => $infos,
        ];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)
            || !isset($data['perDir'], $data['files'], $data['infos'])
            || !is_array($data['perDir'])
            || !is_array($data['files'])
            || !is_array($data['infos'])
        ) {
            return null;
        }

        $perDir = [];
        foreach ($data['perDir'] as $dir => $fingerprint) {
            if (!is_string($dir)
                || !is_array($fingerprint)
                || !isset($fingerprint['mtime'], $fingerprint['fileCount'])
                || !is_int($fingerprint['mtime'])
                || !is_int($fingerprint['fileCount'])
            ) {
                return null;
            }

            $perDir[$dir] = ['mtime' => $fingerprint['mtime'], 'fileCount' => $fingerprint['fileCount']];
        }

        $files = [];
        foreach ($data['files'] as $file) {
            if (!is_array($file)
                || !isset($file['file'], $file['mtime'])
                || !is_string($file['file'])
                || !is_int($file['mtime'])
            ) {
                return null;
            }

            $files[] = ['file' => $file['file'], 'mtime' => $file['mtime']];
        }

        $infos = [];
        foreach ($data['infos'] as $info) {
            if (!is_array($info)
                || !isset($info['file'], $info['namespace'], $info['dependencies'])
                || !is_string($info['file'])
                || !is_string($info['namespace'])
                || !is_array($info['dependencies'])
            ) {
                return null;
            }

            /** @var list<string> $dependencies */
            $dependencies = array_values($info['dependencies']);

            $infos[] = new NamespaceInformation(
                $info['file'],
                $info['namespace'],
                $dependencies,
                (bool) ($info['isPrimaryDefinition'] ?? true),
            );
        }

        // Guard against a truncated/partial write where counts disagree.
        if (count($infos) !== count($data['infos']) || count($files) !== count($data['files'])) {
            return null;
        }

        return new self($perDir, $files, $infos);
    }
}
