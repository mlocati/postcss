<?php

namespace PostCSS\Exception;

class UndefinedProperty extends Exception
{
    /**
     * @var object
     */
    protected $object;

    /**
     * @var string
     */
    protected $propertyName;

    /**
     * @param object $object
     * @param string $propertyName
     * @param int|null $code
     * @param \Exception|null $previous
     */
    public function __construct($object, $propertyName, $code = null, $previous = null)
    {
        $this->object = $object;
        $this->propertyName = $propertyName;
        parent::__construct(sprintf('%1$s does not have a property named %2$s', get_class($object), $propertyName));
    }

    /**
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }
}
