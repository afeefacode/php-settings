<?php

namespace Afeefa\Component\Settings\Test;

class TestReflectionUtils
{
    public static function getProperty($object, string $name)
    {
        $properties = static::getProperties($object);
        return $properties[$name] ?? null;
    }

    public static function invokeMethod($object, string $methodName, ...$arguments)
    {
        $rm = new \ReflectionMethod($object, $methodName);
        $rm->setAccessible(true);
        return $rm->invoke($object, ...$arguments);
    }

    private static function getProperties($object)
    {
        $properties = [];
        try {
            $rc = new \ReflectionClass($object);
            do {
                $rp = [];
                /** \ReflectionProperty $p */
                foreach ($rc->getProperties() as $p) {
                    $p->setAccessible(true);
                    $rp[$p->getName()] = $p->getValue($object);
                }
                $properties = array_merge($rp, $properties);
            } while ($rc = $rc->getParentClass());
        } catch (\ReflectionException $e) {
        }
        return $properties;
    }
}
