<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\PhpKeywords;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;

use function count;
use function explode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;

final class NsSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const string INVALID_NAMESPACE_MESSAGE = <<<'TXT'
Invalid namespace. A valid namespace name starts with a letter or underscore,
followed by any number of letters, numbers, underscores, or dashes.
Elements are split by a backslash.
TXT;

    private const string NAMESPACE_PART_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\-\x7f-\xff]*$/';

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): NsNode
    {
        $nsSymbol = $list->get(1);
        if (!($nsSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'ns must be a Symbol", $list);
        }

        $ns = $nsSymbol->getName();
        $parts = explode('\\', $ns);

        $this->assertValidNamespace($parts, $nsSymbol);

        foreach ($parts as $part) {
            if ($this->isPHPKeyword($part)) {
                throw AnalyzerException::withLocation(
                    sprintf("The namespace is not valid. The part '%s' cannot be used because it is a reserved keyword.", $part),
                    $list,
                );
            }
        }

        $this->analyzer->setNamespace($ns);

        $requireNs = [];
        $requireFiles = [];
        for ($forms = $list->rest()->cdr(); $forms !== null; $forms = $forms->cdr()) {
            $import = $forms->first();

            if (!($import instanceof PersistentListInterface)) {
                throw AnalyzerException::withLocation("Import in 'ns must be Lists.", $list);
            }

            $value = $import->get(0);

            /** @var PersistentListInterface $import */
            if ($this->isKeywordWithName($value, 'use')) {
                $this->analyzeUse($ns, $import);
            } elseif ($this->isKeywordWithName($value, 'require')) {
                $requireNs[] = $this->analyzeRequire($ns, $import);
            } elseif ($this->isKeywordWithName($value, 'require-file')) {
                $requireFiles[] = $this->analyzeRequireFile($import);
            } elseif ($value instanceof Keyword) {
                throw AnalyzerException::withLocation(
                    sprintf("Unexpected keyword %s encountered in 'ns. Expected :use or :require.", $value->getName()),
                    $value,
                );
            }
        }

        if (Phel::getDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE)) {
            $replSymbol = Symbol::create('phel\\repl');
            $this->analyzer->addRequireAlias($ns, Symbol::create('repl'), $replSymbol);
            $this->analyzer->addRefers(
                $ns,
                [
                    Symbol::create('doc'),
                    Symbol::create('require'),
                    Symbol::create('use'),
                    Symbol::create('print-colorful'),
                    Symbol::create('println-colorful'),
                ],
                $replSymbol,
            );
        }

        return new NsNode($ns, $requireNs, $requireFiles, $list->getStartLocation());
    }

    private function isPHPKeyword(string $w): bool
    {
        return in_array($w, PhpKeywords::KEYWORDS, true);
    }

    private function isKeywordWithName(mixed $x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }

    private function analyzeUse(string $ns, PersistentListInterface $import): void
    {
        $useSymbol = $import->get(1);
        if (!($useSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation('First argument in :use must be a symbol.', $import);
        }

        if ($useSymbol->getName()[0] !== '\\') {
            $useSymbol = Symbol::createForNamespace($useSymbol->getNamespace(), '\\' . $useSymbol->getName());
        }

        $useData = Phel::map(...$import->toArray());
        $alias = $this->extractAlias($useData, $import, 'use');
        $this->analyzer->addUseAlias($ns, $alias, $useSymbol);
    }

    private function extractAlias(
        PersistentMapInterface $requireData,
        PersistentListInterface $import,
        string $type,
    ): Symbol {
        $alias = $requireData[Keyword::create('as')];

        if ($alias) {
            if (!($alias instanceof Symbol)) {
                throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
            }

            return $alias;
        }

        $alias2 = $requireData[Keyword::create($type)];

        if ($alias2) {
            if (!($alias2 instanceof Symbol)) {
                throw AnalyzerException::withLocation(
                    sprintf('First argument in :%s must be a symbol.', $type),
                    $import,
                );
            }

            $parts = explode('\\', $alias2->getName());
            return Symbol::create($parts[count($parts) - 1]);
        }

        throw AnalyzerException::withLocation('Cannot extract alias', $import);
    }

    /**
     * @return list<Symbol>
     */
    private function extractRefer(PersistentMapInterface $requireData, PersistentListInterface $import): array
    {
        $refer = $requireData[Keyword::create('refer')];

        if ($refer === null) {
            return [];
        }

        if (!$refer instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Refer must be a vector', $import);
        }

        $result = [];
        /** @var PersistentListInterface<mixed> $refer */
        foreach ($refer as $ref) {
            if (!$ref instanceof Symbol) {
                throw AnalyzerException::withLocation('Each refer element must be a Symbol', $import);
            }

            $result[] = $ref;
        }

        return $result;
    }

    private function analyzeRequire(string $ns, PersistentListInterface $import): Symbol
    {
        $requireSymbol = $import->get(1);
        if (!($requireSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation('First argument in :require must be a symbol.', $import);
        }

        $requireData = Phel::map(...$import->toArray());
        $alias = $this->extractAlias($requireData, $import, 'require');
        $referSymbols = $this->extractRefer($requireData, $import);

        $this->analyzer->addRequireAlias($ns, $alias, $requireSymbol);
        $this->analyzer->addRefers($ns, $referSymbols, $requireSymbol);

        return $requireSymbol;
    }

    private function analyzeRequireFile(PersistentListInterface $import): string
    {
        $file = $import->get(1);
        if (!is_string($file)) {
            throw AnalyzerException::withLocation('First argument in :require-file must be a string.', $import);
        }

        return $file;
    }

    /**
     * @param list<string> $parts
     */
    private function assertValidNamespace(array $parts, Symbol $nsSymbol): void
    {
        foreach ($parts as $part) {
            if ($part === '' || !$this->isValidNamespacePart($part)) {
                throw AnalyzerException::withLocation(self::INVALID_NAMESPACE_MESSAGE, $nsSymbol);
            }
        }
    }

    private function isValidNamespacePart(string $part): bool
    {
        return preg_match(self::NAMESPACE_PART_PATTERN, $part) === 1;
    }
}
