<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer;

class Set extends PhelArray implements ArrayAccess, Countable, Iterator, ICons, ISlice, ICdr, IRest, IPop, IRemove, IPush, IConcat
{

    /**
     * Constructor
     *
     * @param mixed[] $data A list of all values
     */
    public function __construct(array $data)
    {
        parent::__construct([]);
        $this->setData($data);
    }

    /**
     * Create a new array.
     *
     * @param mixed[] $values A list of all values
     *
     * @return Set
     */
    public static function create(...$values): Set
    {
        return new Set($values);
    }

    public function push($x): IPush
    {
        if (!in_array($x, $this->data)) {
            $this->data[] = $x;
        }
        return $this;
    }

    public function concat($xs): IConcat
    {
        foreach ($xs as $x) {
            $this->push($x);
        }
        return $this;
    }

    public function union(Set $set): IConcat
    {
        return $this->concat($set->toPhpArray());
    }

    public function intersection(Set $set): IConcat
    {
        return $this->applySet($set, 'array_intersect');
    }

    public function difference(Set $set): IConcat
    {
        $rightDiff = new Set($set->toPhpArray());
        $rightDiff->applySet($this, 'array_diff');
        $this->applySet($set, 'array_diff');
        return $this->union($rightDiff);
    }

    public function equals($other): bool
    {
        return $this->toPhpArray() == $other->toPhpArray();
    }

    private function applySet(Set $set, callable $operation): Set
    {
        $this->setData($operation($this->data, $set->toPhpArray()));
        return $this;
    }

    private function setData(array $data)
    {
        $this->data = array_values(array_unique($data));
    }
}
