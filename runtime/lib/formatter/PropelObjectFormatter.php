<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Object formatter for Propel query
 * format() returns a PropelObjectCollection of Propel model objects
 *
 * @author     Francois Zaninotto
 * @version    $Revision$
 * @package    propel.runtime.formatter
 */
class PropelObjectFormatter extends PropelFormatter
{
    protected $collectionName = 'PropelObjectCollection';

    private $mainObject;

    public function format(PDOStatement $stmt)
    {
        $this->checkInit();
        if ($class = $this->collectionName) {
            $collection = new $class();
            $collection->setModel($this->class);
            $collection->setFormatter($this);
        } else {
            $collection = array();
        }
        if ($this->isWithOneToMany()) {
            if ($this->hasLimit) {
                throw new PropelException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
            }
            // Dedup by a stringified PK hash instead of in_array() over a growing list,
            // which was O(n^2) in the number of rows for one-to-many with() hydration.
            // serialize() keeps composite PKs collision-free; scalar PKs stay cheap.
            $pkSeen = array();
            $objectsByPks = array();
            $poolingDisabled = (false === Propel::isInstancePoolingEnabled());
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $object = $this->getAllObjectsFromRow($row);
                $pk = $object->getPrimaryKey();
                $pkHash = is_array($pk) ? serialize($pk) : (string) $pk;

                if ($poolingDisabled) {
                    if (isset($objectsByPks[$pkHash])) {
                        $this->mainObject = $objectsByPks[$pkHash];
                        $object = $this->getAllObjectsFromRow($row);
                    }

                    $objectsByPks[$pkHash] = $object;
                }

                if (!isset($pkSeen[$pkHash])) {
                    $collection[] = $object;
                    $pkSeen[$pkHash] = true;
                }
            }
        } else {
            // only many-to-one relationships
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $collection[] = $this->getAllObjectsFromRow($row);
            }
        }
        $stmt->closeCursor();

        return $collection;
    }

    public function formatOne(PDOStatement $stmt)
    {
        $this->checkInit();

        $result = null;
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $this->mainObject = $result;
            $result = $this->getAllObjectsFromRow($row);
        }

        $stmt->closeCursor();

        return $result;
    }

    public function isObjectFormatter()
    {
        return true;
    }

    /**
     * Hydrates a series of objects from a result row
     * The first object to hydrate is the model of the Criteria
     * The following objects (the ones added by way of ModelCriteria::with()) are linked to the first one
     *
     * @param array $row associative array indexed by column number,
     *                   as returned by PDOStatement::fetch(PDO::FETCH_NUM)
     *
     * @return BaseObject
     */
    public function getAllObjectsFromRow($row)
    {
        // get the main object
        // Direct static dispatch instead of call_user_func(array(...)) — this line runs
        // once per fetched row for every object query fleet-wide; avoids the per-row
        // callable-array allocation and call_user_func indirection.
        $peer = $this->peer;
        list($obj, $col) = $peer::populateObject($row);

        if (null !== $this->mainObject) {
            $obj = $this->mainObject;
        }

        // related objects added using with()
        foreach ($this->getWith() as $modelWith) {
            $withPeer = $modelWith->getModelPeerName();
            list($endObject, $col) = $withPeer::populateObject($row, $col);

            if (null !== $modelWith->getLeftPhpName() && !isset($hydrationChain[$modelWith->getLeftPhpName()])) {
                continue;
            }

            if ($modelWith->isPrimary()) {
                $startObject = $obj;
            } elseif (isset($hydrationChain)) {
                $startObject = $hydrationChain[$modelWith->getLeftPhpName()];
            } else {
                continue;
            }
            // as we may be in a left join, the endObject may be empty
            // in which case it should not be related to the previous object
            if (null === $endObject || $endObject->isPrimaryKeyNull()) {
                if ($modelWith->isAdd()) {
                    // Variable-method dispatch — drops the per-row callable-array
                    // allocation + call_user_func indirection (see 103fcede).
                    $initMethod = $modelWith->getInitMethod();
                    $startObject->$initMethod(false);
                }
                continue;
            }
            if (isset($hydrationChain)) {
                $hydrationChain[$modelWith->getRightPhpName()] = $endObject;
            } else {
                $hydrationChain = array($modelWith->getRightPhpName() => $endObject);
            }

            $relationMethod = $modelWith->getRelationMethod();
            $startObject->$relationMethod($endObject);

            if ($modelWith->isAdd()) {
                $resetPartialMethod = $modelWith->getResetPartialMethod();
                $startObject->$resetPartialMethod(false);
            }
        }

        // columns added using withColumn()
        foreach ($this->getAsColumns() as $alias => $clause) {
            $obj->setVirtualColumn($alias, $row[$col]);
            $col++;
        }

        return $obj;
    }
}
