<?php

namespace SilverStripe\Maintain;

/**
 * Provides a base class for any utility classes, including things like singleton accessors
 */
abstract class Utility
{
    /**
     * @var Utility[]
     */
    protected static $instances = [];

    /**
     * Get a singleton instance
     *
     * @return self
     */
    public static function instance() : self
    {
        $class = get_called_class();

        if (!isset(static::$instances[$class])) {
            static::$instances[$class] = new $class();
        }
        return static::$instances[$class];
    }
}
