<?php

namespace SilverStripe\Maintain\Loader;

use SilverStripe\Maintain\Loader;
use const MAINTAIN_BASE_DIR;

class TemplateLoader extends Loader
{
    /**
     * @var string
     */
    protected $templatesDir = 'templates';

    /**
     * @param string $filename
     * @return string
     */
    public function get($filename) : string
    {
        $fileData = @file_get_contents(MAINTAIN_BASE_DIR . '/' . $this->getTemplatesDir() . '/' . $filename);

        return $fileData ?: '';
    }

    /**
     * @return string
     */
    public function getTemplatesDir() : string
    {
        return $this->templatesDir;
    }

    /**
     * @param string $templatesDir
     * @return $this
     */
    public function setTemplatesDir($templatesDir) : self
    {
        $this->templatesDir = $templatesDir;
        return $this;
    }
}
