<?php

namespace InternetPrintingProtocol\Factory;

use InternetPrintingProtocol\DataClass\Arguments;
use InternetPrintingProtocol\Interfaces\CommandInterface;

class CommandFactory
{
    public function create(string $command, Arguments $argumentHandler): CommandInterface
    {
        /* @todo Implement the command factory */
    }
}
