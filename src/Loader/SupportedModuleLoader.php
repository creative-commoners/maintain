<?php

namespace SilverStripe\Maintain\Loader;

use SilverStripe\Maintain\FilterInterface;
use SilverStripe\Maintain\Loader;

class SupportedModuleLoader extends Loader
{
    /**
     * @var FilterInterface
     */
    protected $filter;

    /**
     * The URL to retrieve the JSON data for supported modules
     *
     * @var string
     */
    protected $dataUrl = 'https://raw.githubusercontent.com/silverstripe/supported-modules/gh-pages/modules.json';

    /**
     * Returns an array of supported modules, with the GitHub slug as the value and the Composer package
     * name as the key
     *
     * @return string[]
     */
    public function getModules() : array
    {
        $data = $this->getModuleData();
        $modules = json_decode($data, true) ?: [];

        if ($this->getFilter()) {
            $modules = $this->getFilter()->filter($modules);
        }

        return array_column($modules, 'github', 'composer');
    }

    /**
     * @return string
     */
    protected function getModuleData() : string
    {
        $data = file_get_contents($this->getDataUrl());
        return $data ?: '';
    }

    /**
     * @param string $dataUrl
     * @return $this
     */
    public function setDataUrl($dataUrl) : self
    {
        $this->dataUrl = $dataUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getDataUrl() : string
    {
        return $this->dataUrl;
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function setFilter(FilterInterface $filter) : self
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter() : FilterInterface
    {
        return $this->filter;
    }
}
