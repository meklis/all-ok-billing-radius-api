<?php


namespace envPHP\service\Exceptions;


use Throwable;

class NotFoundException extends \Exception
{
    protected $type;
    function __construct($message = "", $code = 0, $type = '', Throwable $previous = null)
    {
        $this->type = $type;
        parent::__construct($message, $code, $previous);
    }
    function getType() {
        return $this->type;
    }
}