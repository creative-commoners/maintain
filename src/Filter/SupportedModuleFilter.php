<?php

namespace SilverStripe\Maintain\Filter;

use SilverStripe\Maintain\FilterInterface;

class SupportedModuleFilter implements FilterInterface
{
    /**
     * @var string
     */
    const TYPE_SUPPORTED_MODULE = 'supported-module';

    /**
     * Filters an array of modules by "supported-module" types
     *
     * @param array $input
     * @return array
     */
    public function filter(array $input)
    {
        return array_filter($input, function ($module) {
            return isset($module['type']) && $module['type'] === self::TYPE_SUPPORTED_MODULE;
        });
    }
}
