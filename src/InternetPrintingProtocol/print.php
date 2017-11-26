<?php

use \InternetPrintingProtocol\Utils\ArgumentHandler;
use InternetPrintingProtocol\Utils\Formatter\InputFormatter;

array_shift($argv);

$argumentHandler = new ArgumentHandler(new InputFormatter());

$argumentHandler->handle($argv);
