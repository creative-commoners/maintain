<?php

namespace SilverStripe\Maintain\Utility;

use SilverStripe\Maintain\Utility;

class SupportedModuleLoader extends Utility
{
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
        $modules = json_decode($data, true);
        if (!$modules) {
            return [];
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
}
