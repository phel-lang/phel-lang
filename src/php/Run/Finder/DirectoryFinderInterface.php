<?php

declare(strict_types=1);

namespace Phel\Run\Finder;

interface DirectoryFinderInterface
{
    /**
     * @return list<string>
     */
    public function getAbsoluteSourceDirectories(): array;

    /**
     * @return list<string>
     */
    public function getAbsoluteTestDirectories(): array;

    /**
     * @return list<string>
     */
    public function getAbsoluteVendorSourceDirectories(): array;
}
