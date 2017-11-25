<?php

namespace InternetPrintingProtocol\Command;

use Monolog\Logger;
use InternetPrintingProtocol\Interfaces\CommandInterface;
use InternetPrintingProtocol\Dataclass\ArgumentHandler;

abstract class AbstractCommand implements CommandInterface
{
    /**
     * @var ArgumentHandler
     */
    protected $argumentHandler;

    private function __construct(ArgumentHandler $argumentHandler)
    {
        $this->argumentHandler = $argumentHandler;
    }

    public static function create(ArgumentHandler $argumentHandler)
    {
        return new self($argumentHandler);
    }

    abstract public function execute(Logger $logger): bool;
}
