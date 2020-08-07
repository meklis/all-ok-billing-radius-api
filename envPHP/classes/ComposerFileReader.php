<?php


namespace envPHP\classes;


class ComposerFileReader
{
    protected $composer;
    function __construct($path)
    {
        $this->composer = json_decode(file_get_contents($path), TRUE);
    }
    function getVersion() {
        return $this->composer['version'];
    }
    function getProjectName() {
        return $this->composer['name'];
    }
    function getAuthors() {
        return $this->composer['authors'];
    }
}