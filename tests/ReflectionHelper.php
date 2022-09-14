<?php

namespace EnvoyMediaGroup\Columna\Tests;

use Exception;
use ReflectionClass;
use ReflectionException;

class ReflectionHelper {

    /**
     * @param object $Object
     * @param string $method_name
     * @param array|null $arguments
     * @throws ReflectionException
     * @return mixed
     */
    public static function invokeProtectedMethod(object $Object, string $method_name, ?array $arguments = null) {
        if ($method_name === '') {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " method name cannot be empty.");
        }

        $class = self::getOriginalClassFromPhpUnitMockClass(get_class($Object));
        $ReflectionClass = new ReflectionClass($class);
        $ReflectionMethod = $ReflectionClass->getMethod($method_name);
        if ($ReflectionMethod->isStatic()) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." class '{$class}' method '{$method_name}' is a static method. Please use invokeProtectedStaticMethod() instead.");
        }
        $ReflectionMethod->setAccessible(true);

        if (isset($arguments)) {
            return $ReflectionMethod->invokeArgs($Object, $arguments);
        } else {
            return $ReflectionMethod->invoke($Object);
        }
    }

    /**
     * @param string $class
     * @param string $method_name
     * @param array|null $arguments
     * @throws ReflectionException
     * @return mixed
     */
    public static function invokeProtectedStaticMethod(string $class, string $method_name, ?array $arguments = null) {
        if ($method_name === '') {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " method name cannot be empty.");
        }

        $class = self::getOriginalClassFromPhpUnitMockClass($class);
        $ReflectionClass = new ReflectionClass($class);
        $ReflectionMethod = $ReflectionClass->getMethod($method_name);
        if (!$ReflectionMethod->isStatic()) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." class '{$class}' method '{$method_name}' is not a static method. Please use invokeProtectedMethod() instead.");
        }
        $ReflectionMethod->setAccessible(true);

        if (isset($arguments)) {
            return $ReflectionMethod->invokeArgs(null, $arguments);
        } else {
            return $ReflectionMethod->invoke(null);
        }
    }

    /**
     * @param object $Object
     * @param string $property_name
     * @param mixed $value
     * @throws Exception
     * @throws ReflectionException
     */
    public static function setProtectedProperty(object $Object, string $property_name, $value): void {
        if ($property_name === '') {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " property name cannot be empty.");
        }
        //$value can be anything.

        $class = self::getOriginalClassFromPhpUnitMockClass(get_class($Object));
        $ReflectionClass = new ReflectionClass($class);
        $ReflectionProperty = $ReflectionClass->getProperty($property_name);
        $ReflectionProperty->setAccessible(true);

        $ReflectionProperty->setValue($Object, $value);
    }

    /**
     * @param object $Object
     * @param string $property_name
     * @throws Exception
     * @return mixed
     */
    public static function getProtectedProperty(object $Object, string $property_name) {
        if ($property_name === '') {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " property name cannot be empty.");
        }

        $class = self::getOriginalClassFromPhpUnitMockClass(get_class($Object));
        $ReflectionClass = new ReflectionClass($class);
        $ReflectionProperty = $ReflectionClass->getProperty($property_name);
        $ReflectionProperty->setAccessible(true);

        return $ReflectionProperty->getValue($Object);
    }

    /**
     * @param string $class
     * @throws Exception
     * @return string
     */
    protected static function getOriginalClassFromPhpUnitMockClass(string $class): string {
        $matches = [];
        if (preg_match("/^Mock_(.*)_[0-9a-f]{8}$/", $class, $matches) === 1) {
            return $matches[1]; //get original class from PHPUnit mock class
        }
        return $class;
    }

}