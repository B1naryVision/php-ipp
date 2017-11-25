<?php

namespace InternetPrintingProtocol\Interfaces;

use Monolog\Logger;

interface CommandInterface
{
    public static function create(array $inputs);

    public function execute(Logger $logger);
}
