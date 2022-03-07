<?php

namespace Le\Seriale;

use ReflectionClass, ReflectionNamedType, ReflectionException;
use UnitEnum;
use DateTimeInterface;
use Ramsey\Collection\AbstractCollection;

class Seriale
{

    public function extract(object $object): string
    {
        return json_encode($this->extractor($object));
    }

    private function extractor($value)
    {
        // not an object - just passing it through
        if (!is_object($value)) {
            return $value;
        }

        // value is an enum object
        if($value instanceof UnitEnum){
            return $value->name;
        }

        // object implements datetime interface, pull out the value needed for reinitialization
        if($value instanceof DateTimeInterface){
            return $value->getTimestamp();
        }

        // very likely an object that is a collection of objects
        if (is_iterable($value)) {
            // go through each of its elements and recursively extract their props
            foreach ($value as $subValue)
                $output[] = $this->extractor($subValue);
            return $output ?? [];
        }

        // if none of above, we're just going to pull out each public prop out of the object
        foreach (get_object_vars($value) as $prop => $val)
            $output[$prop] = $this->extractor($val);

        return $output ?? [];
    }

    /**
     * @throws ReflectionException
     */
    public function hydrate(string $class, string $extracted): object
    {
        return $this->hydrator($class, json_decode($extracted, true));
    }

    /**
     * @throws ReflectionException
     */
    private function hydrator(string|ReflectionClass $class, $value): object
    {
        if (is_string($class)) $class = new ReflectionClass($class);

        if ($class->isEnum()) { // type is enum
            return $class->getConstant($value);
        }

        // type implements datatimeinterface, we need to create the object from timestamp
        if($class->implementsInterface(DateTimeInterface::class)){
            $className = $class->name;
            return $className::createFromFormat('U', $value);
        }

        // type is object collection
        if (class_exists(AbstractCollection::class) && $class->isSubclassOf(AbstractCollection::class)) {
            $output = $class->newInstanceWithoutConstructor();
            foreach ($value as $subValue) $output->add($this->hydrator($output->getType(), $subValue));
            return $output;
        }

        // type is generic class, we're just going to go through its props recursively
        $instance = $class->newInstanceWithoutConstructor();

        foreach ($value as $prop => $val) {
            $propType = $class->getProperty($prop)->getType();

            // prop is either a non-object type or a complex type - we can't do anything about it - just assign its value
            if (!$propType instanceof ReflectionNamedType || $propType->isBuiltin()) {
                $instance->$prop = $val;
                continue;
            }

            // type is any other object, pass it down for recursion
            $instance->$prop = $this->hydrator($propType->getName(), $val);
        }

        return $instance;
    }

}
