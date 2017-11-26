<?php

declare (strict_types=1);

namespace InternetPrintingProtocol\Utils;

use InternetPrintingProtocol\Factory\CommandFactory;
use InternetPrintingProtocol\Utils\Formatter\InputFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ArgumentHandler
{
    /**
     * @var InputFormatter
     */
    private $formatter;

    public function __construct(InputFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function handle(array $arguments)
    {
        if ($this->hasHelpAsArgument($arguments)) {
            /* @todo Add a help class to output the commands and their arguments */

            return;
        }

        $arguments = $this->formatter->format($arguments);

        $this->executeCommand($arguments);
    }

    private function hasHelpAsArgument(array $arguments): bool
    {
        $hasHelpAsArgument = false;

        foreach ($arguments as $argument) {
            if (\strpos($argument, '--help') === true || \strpos($argument, '-h') === true) {
                $hasHelpAsArgument = true;
            }
        }

        return $hasHelpAsArgument;
    }

    private function executeCommand(array $arguments)
    {
        $logger = $this->createLogger($arguments['command']);

        $commandFactory = new CommandFactory();
        $command = $commandFactory->create($arguments['command'], $arguments['argument']);

        try {
            $command->execute();
        } catch (\Exception $exception) {
            $logger->addError($exception->getMessage());

            return;
        }
    }

    private function createLogger(string $commandName): Logger
    {
        $logger = new Logger($commandName);
        $logger->pushHandler(new StreamHandler(
            'php://stdout',
            Logger::WARNING
        ));

        return $logger;
    }
}
