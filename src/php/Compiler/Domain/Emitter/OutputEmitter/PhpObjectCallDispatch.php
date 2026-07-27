<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

/**
 * Which PHP member-access operator a `PhpObjectCallNode` emits.
 *
 * `Runtime` exists because a class name can be a value: `(.cases c)` with `c`
 * bound to `"\\App\\Status"` has to reach `$c::cases()`, while the same form on
 * an object has to reach `$c->cases()`. When the analyzer cannot prove which,
 * the emitter defers to `is_string()`. That is safe in one direction only:
 * `$string->m()` is never valid PHP, so no working program changes meaning.
 *
 * @internal
 */
enum PhpObjectCallDispatch
{
    case Instance;
    case StaticClass;
    case Runtime;
}
