<?php

/**
 * An abstract source to bulk load records from.
 * Provides an iterator for retrieving records from.
 *
 * Useful for holiding source configuration state.
 */
namespace ImportExport\Bulkloader\Sources;

abstract class BulkLoaderSource implements \IteratorAggregate
{

    /**
     * Provide iterator for bulk loading from.
     * Records are expected to be 1 dimensional key-value arrays.
     * @return Iterator
     */
    abstract public function getIterator();
}
