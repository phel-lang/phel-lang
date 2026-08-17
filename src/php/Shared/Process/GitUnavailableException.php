<?php

declare(strict_types=1);

namespace Phel\Shared\Process;

use RuntimeException;

/**
 * The directory is not inside a git repository, git is not on the PATH, or
 * the ref does not exist.
 */
final class GitUnavailableException extends RuntimeException {}
