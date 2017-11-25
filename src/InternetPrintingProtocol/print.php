<?php

$logger = new Monolog\Logger($arguments['command']);
$logger->pushHandler(
    new Monolog\Handler\StreamHandler(
        'php://stdout',
        Monolog\Logger::WARNING
    )
);

$inputFormatter = new InternetPrintingProtocol\Utils\Formatter\InputFormatter();

unset($argv[0]);

try {
    $arguments = $inputFormatter->format($argv);
} catch (\UnexpectedValueException $exception) {
    $logger->addError($exception->getMessage());

    return;
}

$commandFactory = new InternetPrintingProtocol\Factory\CommandFactory();
$command = $commandFactory->create($arguments['command'], $arguments['argument']);

$logger = new Monolog\Logger($arguments['command']);
$logger->pushHandler(
    new Monolog\Handler\StreamHandler(
        'php://stdout',
        Monolog\Logger::WARNING
    )
);

try {
    $command->execute($logger);
} catch (\Exception $exception) {
    $logger->addError($exception->getMessage());

    return;
}
