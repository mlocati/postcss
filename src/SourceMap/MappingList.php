<?php

namespace PostCSS\SourceMap;

/**
 * A data structure to provide a sorted view of accumulated mappings in a performance conscious manner.
 * It trades a neglibable overhead in general case for a large speedup in case of mappings being added in order.
 *
 * @link https://github.com/mozilla/source-map/blob/master/lib/mapping-list.js
 */
class MappingList
{
    /**
     * @var Mapping[]
     */
    protected $array;

    /**
     * @var bool
     */
    protected $sorted;

    /**
     * @var MappingList
     */
    protected $last;

    public function __construct()
    {
        $this->array = [];
        $this->sorted = true;
        $this->last = new Mapping([
            'generatedLine' => -1,
            'generatedColumn' => 0,
        ]);
    }

    /**
     * Iterate through internal items.
     *
     * NOTE: The order of the mappings is NOT guaranteed.
     *
     * @param callable $aCallback
     * @param mixed $aThisArg
     */
    public function unsortedForEach($aCallback, $aThisArg)
    {
        foreach ($this->array as $item) {
            call_user_func($aCallback, $item, $aThisArg);
        }
    }

    /**
     * Add the given source mapping.
     *
     * @param Mapping aMapping
     */
    public function add(Mapping $aMapping)
    {
        if (static::generatedPositionAfter($this->last, $aMapping)) {
            $this->last = $aMapping;
            $this->array[] = $aMapping;
        } else {
            $this->sorted = false;
            $this->array[] = $aMapping;
        }
    }

    /**
     * Returns the flat, sorted array of mappings. The mappings are sorted by
     * generated position.
     *
     * WARNING: This method returns internal data without copying, for
     * performance. The return value must NOT be mutated, and should be treated as
     * an immutable borrow. If you want to take ownership, you must make your own
     * copy.
     */
    public function toArray()
    {
        if ($this->sorted === false) {
            usort($this->array, [Mapping::class, 'compareByGeneratedPositionsInflated']);
            $this->sorted = true;
        }

        return $this->array;
    }

    /**
     * Determine whether mappingB is after mappingA with respect to generated position.
     *
     * @param Mapping $mappingA
     * @param Mapping $mappingB
     *
     * @return bool
     */
    protected static function generatedPositionAfter(Mapping $mappingA, Mapping $mappingB)
    {
        // Optimized for most common case
        $lineA = $mappingA->generatedLine ?: 0;
        $lineB = $mappingB->generatedLine ?: 0;
        $columnA = $mappingA->generatedColumn ?: 0;
        $columnB = $mappingB->generatedColumn ?: 0;

        return $lineB > $lineA || $lineB == $lineA && $columnB >= $columnA || Mapping::compareByGeneratedPositionsInflated(mappingA, mappingB) <= 0;
    }
}
