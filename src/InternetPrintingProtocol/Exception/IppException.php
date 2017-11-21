<?php

declare(strict_types=1);

namespace php-ipp\

class IppException extends \Exception
{
    protected $errorNumber;

    public function __construct($msg, $errorNumber = null)
    {
        parent::__construct($msg);

        $this->errorNumber = $errorNumber;
    }

    public function getErrorFormatted()
    {
        $return = sprintf('[ipp]: %s -- ' . _(' file %s, line %s'),
            $this->getMessage(), $this->getFile(), $this->getLine());
        return $return;
    }

    public function getErrorNumber()
    {
        return $this->errorNumber;
    }
}
