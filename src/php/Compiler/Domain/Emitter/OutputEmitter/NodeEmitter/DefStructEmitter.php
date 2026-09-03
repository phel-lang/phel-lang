<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\PhpAttributeRenderer;

use function assert;
use function count;
use function implode;

/**
 * @internal
 */
final readonly class DefStructEmitter implements NodeEmitterInterface
{
    use EvalGuardedEmitterTrait;
    use PhpAttributeEmitterTrait;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefStructNode);

        $this->emitGuardedTypeDeclaration(
            $node->getNamespace(),
            $node->getName()->getName(),
            'class_exists',
            $node->getStartSourceLocation(),
            function () use ($node): void {
                $this->emitClassBody($node);
            },
        );
    }

    private function emitClassBody(DefStructNode $node): void
    {
        $this->emitClassHeader($node);
        $this->emitAllowedKeys($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
        $this->emitReadonlyPut($node);
        $this->emitInterfaces($node);
        $this->emitJsonSerialize($node);
        $this->emitClassFooter($node);
    }

    private function emitClassHeader(DefStructNode $node): void
    {
        $this->emitDocBlock($node->getName()->getMeta(), $node->getStartSourceLocation());
        $this->emitAttributes($node->getName()->getMeta(), $node->getStartSourceLocation());

        $this->outputEmitter->emitStr(
            'final class ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' extends \Phel\Lang\Collections\Struct\AbstractPersistentStruct',
            $node->getStartSourceLocation(),
        );

        $interfaces = $this->interfaceNames($node);
        if ($interfaces !== []) {
            $this->outputEmitter->emitStr(' implements ' . implode(', ', $interfaces));
        }

        $this->outputEmitter->emitLine(' {');

        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine();
    }

    /**
     * The full `implements` list: the Phel protocol interfaces plus the
     * opt-in PHP marker interfaces requested via the struct-name meta
     * (`^{:php/json true}` => `\JsonSerializable`, `^{:php/stringable true}`
     * => `\Stringable`, satisfied by the inherited `__toString`).
     *
     * @return list<string>
     */
    private function interfaceNames(DefStructNode $node): array
    {
        $names = [];
        foreach ($node->getInterfaces() as $defStruct) {
            // A `:php` bare-method block carries an empty interface name: its
            // methods are emitted on the class but it adds no `implements` entry.
            if ($defStruct->getAbsoluteInterfaceName() !== '') {
                $names[] = $defStruct->getAbsoluteInterfaceName();
            }
        }

        $meta = $node->getName()->getMeta();
        if ($this->metaFlag($meta, 'json')) {
            $names[] = '\JsonSerializable';
        }

        if ($this->metaFlag($meta, 'stringable')) {
            $names[] = '\Stringable';
        }

        return $names;
    }

    /**
     * Emits a `jsonSerialize(): array` returning the field map when the struct
     * opts in with `^{:php/json true}`.
     */
    private function emitJsonSerialize(DefStructNode $node): void
    {
        if (!$this->metaFlag($node->getName()->getMeta(), 'json')) {
            return;
        }

        $pairs = [];
        foreach ($node->getParams() as $param) {
            $pairs[] = "'" . $param->getName() . "' => \$this->" . $this->outputEmitter->mungeEncode($param->getName());
        }

        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('public function jsonSerialize(): array {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('return [' . implode(', ', $pairs) . '];', $node->getStartSourceLocation());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function isReadonly(DefStructNode $node): bool
    {
        return $this->metaFlag($node->getName()->getMeta(), 'readonly');
    }

    /**
     * Reads a boolean `:php/<name>` flag off the struct-name meta.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function metaFlag(?PersistentMapInterface $meta, string $name): bool
    {
        if (!$meta instanceof PersistentMapInterface) {
            return false;
        }

        return $meta->find(Keyword::create($name, 'php')) === true;
    }

    private function emitAllowedKeys(DefStructNode $node): void
    {
        $params = $node->getParams();
        $paramCount = count($params);
        $this->outputEmitter->emitStr('protected const array ALLOWED_KEYS = [', $node->getStartSourceLocation());

        foreach ($params as $i => $param) {
            $this->outputEmitter->emitStr("'" . $param->getName() . "'");
            if ($i < $paramCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine('];', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine();
    }

    private function emitProperties(DefStructNode $node): void
    {
        $readonly = $this->isReadonly($node);
        $params = $node->getParams();
        foreach ($params as $param) {
            $meta = $param->getMeta();
            $this->emitDocBlock($meta, $node->getStartSourceLocation());
            $this->emitAttributes($meta, $node->getStartSourceLocation());

            $this->outputEmitter->emitStr('protected ');
            if ($readonly) {
                $this->outputEmitter->emitStr('readonly ');
            }

            $tag = $this->tagTypeFromMeta($meta);
            if ($tag !== null) {
                $this->outputEmitter->emitStr($tag . ' ');
            } elseif ($readonly) {
                // A `readonly` property must be typed; default the untagged
                // fields of a readonly struct to `mixed`.
                $this->outputEmitter->emitStr('mixed ');
            }

            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(';');
        }

        $this->outputEmitter->emitLine();
    }

    private function emitConstructor(DefStructNode $node): void
    {
        $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());

        $params = $node->getParams();
        foreach ($params as $param) {
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitPhpVariable(Symbol::create('meta'));
        $this->outputEmitter->emitStr(' = null');

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('parent::__construct();');

        foreach ($params as $param) {
            $propertyName = $this->outputEmitter->mungeEncode($param->getName());

            $this->outputEmitter->emitStr('$this->' . $propertyName . ' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('$this->meta = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable(Symbol::create('meta'));
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * Persistent update on a readonly struct can't use the base class'
     * clone-and-write `put` (writing a readonly property from the base scope
     * is illegal), so a readonly struct rebuilds itself through the
     * constructor, which is the only scope allowed to initialise the props.
     */
    private function emitReadonlyPut(DefStructNode $node): void
    {
        if (!$this->isReadonly($node)) {
            return;
        }

        $params = $node->getParams();
        if ($params === []) {
            return;
        }

        $location = $node->getStartSourceLocation();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine(
            'public function put($key, $value): \Phel\Lang\Collections\Map\PersistentMapInterface {',
            $location,
        );
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('$stringKey = $this->validateKey($key);', $location);
        $this->outputEmitter->emitLine('return new self(', $location);
        $this->outputEmitter->increaseIndentLevel();

        foreach ($params as $param) {
            $propertyName = $this->outputEmitter->mungeEncode($param->getName());
            $this->outputEmitter->emitLine(
                "\$stringKey === '" . $propertyName . "' ? \$value : \$this->" . $propertyName . ',',
                $location,
            );
        }

        $this->outputEmitter->emitLine('$this->meta,', $location);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');', $location);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $location);
    }

    private function emitInterfaces(DefStructNode $node): void
    {
        foreach ($node->getInterfaces() as $defStruct) {
            foreach ($defStruct->getMethods() as $method) {
                $this->outputEmitter->emitLine();
                $this->emitDocBlock($method->getName()->getMeta(), $node->getStartSourceLocation());
                $this->emitAttributes($method->getName()->getMeta(), $node->getStartSourceLocation());
                $this->methodEmitter->emit($method->getName()->getName(), $method->getFnNode());
            }
        }
    }

    private function emitClassFooter(DefStructNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
