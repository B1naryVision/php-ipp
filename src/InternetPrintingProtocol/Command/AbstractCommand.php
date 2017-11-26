<?php

namespace InternetPrintingProtocol\Command;

use InternetPrintingProtocol\DataClass\Arguments;
use Monolog\Logger;
use InternetPrintingProtocol\Interfaces\CommandInterface;

abstract class AbstractCommand implements CommandInterface
{
    /**
     * @var Arguments
     */
    protected $argumentHandler;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(Arguments $argumentHandler, Logger $logger)
    {
        $this->argumentHandler = $argumentHandler;
        $this->logger = $logger;
    }
}
