<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Array formatter for Propel select query
 * format() returns a PropelArrayCollection of associative arrays, a string,
 * or an array
 *
 * @author     Benjamin Runnels
 * @version    $Revision$
 * @package    propel.runtime.formatter
 */
class PropelSimpleArrayFormatter extends PropelFormatter
{
    protected $collectionName = 'PropelArrayCollection';

    // Cleaned (unquoted) column names + multi-column flag, computed once per
    // query in format()/formatOne() instead of per row in getStructuredArrayFromRow.
    protected $cleanColumnNames = null;
    protected $isMultiColumn = false;

    /**
     * Snapshot the (fixed-for-the-query) select-column names, stripping the
     * quote chars once, so the per-row hot loop no longer rebuilds array_keys()
     * and str_replace()s every cell. Called from format()/formatOne() after
     * init() has populated asColumns.
     */
    protected function prepareColumnNames()
    {
        $names = array_keys($this->getAsColumns());
        foreach ($names as $i => $n) {
            $names[$i] = str_replace('"', '', $n);
        }
        $this->cleanColumnNames = $names;
        $this->isMultiColumn = count($names) > 1;
    }

    public function format(PDOStatement $stmt)
    {
        $this->checkInit();
        $this->prepareColumnNames();
        if ($class = $this->collectionName) {
            $collection = new $class();
            $collection->setModel($this->class);
            $collection->setFormatter($this);
        } else {
            $collection = array();
        }
        if ($this->isWithOneToMany() && $this->hasLimit) {
            throw new PropelException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
        }
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $collection[] = $this->getStructuredArrayFromRow($row);
        }
        $stmt->closeCursor();

        return $collection;
    }

    public function formatOne(PDOStatement $stmt)
    {
        $this->checkInit();
        $this->prepareColumnNames();
        $result = null;
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $result = $this->getStructuredArrayFromRow($row);
        }
        $stmt->closeCursor();

        return $result;
    }

    public function isObjectFormatter()
    {
        return false;
    }

    public function getStructuredArrayFromRow($row)
    {
        // Lazy fallback keeps this safe if ever called outside format()/formatOne().
        if ($this->cleanColumnNames === null) {
            $this->prepareColumnNames();
        }
        if ($this->isMultiColumn && count($row) > 1) {
            $finalRow = array();
            $names = $this->cleanColumnNames;
            foreach ($row as $index => $value) {
                $finalRow[$names[$index]] = $value;
            }
        } else {
            $finalRow = $row[0];
        }

        return $finalRow;
    }
}
