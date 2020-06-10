<?php

/**
 * Backwards copatible CsvBulkLoader
 * Almost api equivelant to CSVBulkLoader
 */
namespace ImportExport\Bulkloader;

use ImportExport\Bulkloader\Sources\CsvBulkLoaderSource;

class CsvBetterBulkLoader extends BetterBulkLoader
{

    public $delimiter = ',';
    public $enclosure = '"';
    public $hasHeaderRow = true;

    protected function processAll($filepath, $preview = false)
    {
        //configre a CsvBulkLoaderSource
        $source = new CsvBulkLoaderSource();
        $source->setFilePath($filepath);
        $source->setHasHeader($this->hasHeaderRow);
        $source->setFieldDelimiter($this->delimiter);
        $source->setFieldEnclosure($this->enclosure);
        $this->setSource($source);

        return parent::processAll($filepath, $preview);
    }

    public function hasHeaderRow()
    {
        return ($this->hasHeaderRow || isset($this->columnMap));
    }
}
