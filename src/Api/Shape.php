<?php
namespace Triggmine\Api;

/**
 * Base class representing a modeled shape.
 */
class Shape extends AbstractModel
{
    /**
     * Get a concrete shape for the given definition.
     *
     * @param array    $definition
     * @param ShapeMap $shapeMap
     *
     * @return mixed
     * @throws \RuntimeException if the type is invalid
     */
    public static function create(array $definition, ShapeMap $shapeMap)
    {
        static $map = [
            'structure' => 'Triggmine\Api\StructureShape',
            'map'       => 'Triggmine\Api\MapShape',
            'list'      => 'Triggmine\Api\ListShape',
            'timestamp' => 'Triggmine\Api\TimestampShape',
            'integer'   => 'Triggmine\Api\Shape',
            'double'    => 'Triggmine\Api\Shape',
            'float'     => 'Triggmine\Api\Shape',
            'long'      => 'Triggmine\Api\Shape',
            'string'    => 'Triggmine\Api\Shape',
            'byte'      => 'Triggmine\Api\Shape',
            'character' => 'Triggmine\Api\Shape',
            'blob'      => 'Triggmine\Api\Shape',
            'boolean'   => 'Triggmine\Api\Shape'
        ];

        if (isset($definition['shape'])) {
            return $shapeMap->resolve($definition);
        }

        if (!isset($map[$definition['type']])) {
            throw new \RuntimeException('Invalid type: '
                . print_r($definition, true));
        }

        $type = $map[$definition['type']];

        return new $type($definition, $shapeMap);
    }

    /**
     * Get the type of the shape
     *
     * @return string
     */
    public function getType()
    {
        return $this->definition['type'];
    }

    /**
     * Get the name of the shape
     *
     * @return string
     */
    public function getName()
    {
        return $this->definition['name'];
    }
}
