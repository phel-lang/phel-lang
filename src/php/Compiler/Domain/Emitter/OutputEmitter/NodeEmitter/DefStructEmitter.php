<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\PhpAttributeRenderer;

use function assert;
use function count;
use function implode;

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

        if ($this->shouldEmitViaEval()) {
            $this->emitViaEval($node);
        } else {
            $this->emitInline($node);
        }
    }

    /**
     * Captures the class body at compile time and emits it as an `eval()`
     * call guarded by `class_exists`. Works anywhere in the emitted PHP,
     * because `eval` starts a fresh top-level scope.
     */
    private function emitViaEval(DefStructNode $node): void
    {
        $ns = $this->outputEmitter->mungeEncodePhpNs($node->getNamespace());
        $fqcn = $this->buildFqcn($node);

        ob_start();
        $this->emitClassBody($node);
        $classBody = (string) ob_get_clean();

        $this->outputEmitter->emitLine("if (!class_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $evalCode = 'namespace ' . $ns . ";\n" . $classBody;
        $this->outputEmitter->emitLine(
            'eval(' . var_export($evalCode, true) . ');',
            $node->getStartSourceLocation(),
        );

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * In file/cache mode the NsEmitter already declared the namespace at the
     * top of the file, so the class is emitted inline without its own
     * namespace statement.
     */
    private function emitInline(DefStructNode $node): void
    {
        $this->emitClassExistsGuard($node);
        $this->emitClassBody($node);
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function emitClassExistsGuard(DefStructNode $node): void
    {
        $fqcn = $this->buildFqcn($node);
        $this->outputEmitter->emitLine("if (!class_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());
    }

    /**
     * Builds the fully qualified PHP class name for the generated struct,
     * encoding both the namespace and the class name through the munger.
     */
    private function buildFqcn(DefStructNode $node): string
    {
        return $this->outputEmitter->mungeEncodePhpNs($node->getNamespace())
            . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());
    }

    private function emitClassBody(DefStructNode $node): void
    {
        $this->emitClassHeader($node);
        $this->emitAllowedKeys($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
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
        $params = $node->getParams();
        foreach ($params as $param) {
            $meta = $param->getMeta();
            $this->emitDocBlock($meta, $node->getStartSourceLocation());
            $this->emitAttributes($meta, $node->getStartSourceLocation());

            $this->outputEmitter->emitStr('protected ');
            $tag = $this->tagTypeFromMeta($meta);
            if ($tag !== null) {
                $this->outputEmitter->emitStr($tag . ' ');
            }

            $this->outputEmitter->emitPhpVariable($param);
            $this->outputEmitter->emitLine(';');
        }

        $this->outputEmitter->emitLine();
    }

    /**
     * Emits one `#[...]` line per `:php/attr` spec carried by the symbol meta,
     * at the current indentation, before the annotated construct.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function emitAttributes(?PersistentMapInterface $meta, ?SourceLocation $sourceLocation): void
    {
        foreach ($this->phpAttributeLines($this->attributeRenderer, $meta) as $attribute) {
            $this->outputEmitter->emitLine($attribute, $sourceLocation);
        }
    }

    /**
     * Emits the `:php/doc` PHPDoc block carried by the symbol meta, before the
     * annotated construct (above any attributes).
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function emitDocBlock(?PersistentMapInterface $meta, ?SourceLocation $sourceLocation): void
    {
        foreach ($this->phpDocLines($meta) as $line) {
            $this->outputEmitter->emitLine($line, $sourceLocation);
        }
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

    private function emitInterfaces(DefStructNode $node): void
    {
        foreach ($node->getInterfaces() as $defStruct) {
            foreach ($defStruct->getMethods() as $method) {
                $this->outputEmitter->emitLine();
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
