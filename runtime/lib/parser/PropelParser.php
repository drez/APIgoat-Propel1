<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Base class for all parsers. A parser converts data from and to an associative array.
 *
 * @author     Francois Zaninotto (Propel)
 * @author     Jonathan H. Wage <jwage@mac.com> (Doctrine_Parser)
 * @package    propel.runtime.parser
 */
abstract class PropelParser
{
    /**
     * Converts data from an associative array to the parser format.
     *
     * Override in the parser driver.
     *
     * @param array $array Source data to convert
     *
     * @return mixed Converted data, depending on the parser format
     */
    abstract public function fromArray($array);

    /**
     * Converts data from the parser format to an associative array.
     *
     * Override in the parser driver.
     *
     * @param mixed $data Source data to convert, depending on the parser format
     *
     * @return array Converted data
     */
    abstract public function toArray($data);

    public function listFromArray($data)
    {
        return $this->fromArray($data);
    }

    public function listToArray($data)
    {
        return $this->toArray($data);
    }

    /**
     * Factory for getting a parser instance. Only JSON remains: the XML, YAML
     * and CSV parsers (and the bundled sfYaml) were removed.
     *
     * @param string $type Parser type, 'JSON'
     *
     * @return PropelParser A PropelParser subclass instance
     *
     * @throws PropelException
     */
    public static function getParser($type = 'JSON')
    {
        if (strtoupper((string) $type) !== 'JSON') {
            throw new PropelException(sprintf('Unknown parser "%s" (only JSON is supported)', $type));
        }

        return new PropelJSONParser();
    }

    /**
     * Parser for exports: a legacy format name (e.g. a DEFAULT_STRING_FORMAT of
     * 'YAML' baked into classes built before the parser removal) falls back to
     * JSON so __toString()/exportTo() never throw on it.
     *
     * @param string $type
     *
     * @return PropelParser
     */
    public static function getExportParser($type = 'JSON')
    {
        return new PropelJSONParser();
    }
}
