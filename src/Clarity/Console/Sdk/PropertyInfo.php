<?php

namespace Clarity\Console\Sdk;


class PropertyInfo
{
    /**
     * @var \ReflectionProperty
     */
    private $reflection_property;

    /**
     * @var string
     */
    public $name;

    /**
     * @var boolean
     */
    public $is_optional;

    /**
     * @var string
     */
    public $type;
    /**
     * @param \ReflectionProperty $reflectionProperty
     */
    public function __construct(\ReflectionProperty $reflectionProperty)
    {
        $this->reflection_property = $reflectionProperty;
    }

    /**
     * @return PropertyInfo
     */
    public function process()
    {
        $this->name = $this->reflection_property->name;
        $this->type = $this->getType($this->reflection_property->getDocComment());
        $this->is_optional = preg_match('/@optional/', $this->reflection_property->getDocComment()) === 1;
        return $this;
    }
    private function getType($doc)
    {
        preg_match_all('/@var (?:(\$\w+) )?(\w+)/', $doc, $matches);
        return end($matches)[0];
    }

}