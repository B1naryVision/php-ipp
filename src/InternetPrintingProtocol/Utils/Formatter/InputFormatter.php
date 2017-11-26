<?php

namespace InternetPrintingProtocol\Utils\Formatter;

use InternetPrintingProtocol\DataClass\Arguments;

class InputFormatter
{
    public function format(array $arguments): array
    {
        $sortedArguments = [];

        $sortedArguments['command'] = $this->getCommandName($arguments);
        $sortedArguments['argument'] = $this->sortArguments($arguments);

        return $sortedArguments;
    }

    private function getCommandName(array $arguments): string
    {
        $commandName = null;

        foreach ($arguments as $argument) {
            if (\strpos($argument, '--') === false) {
                $commandName = $argument;
            }
        }

        if ($commandName === null) {
            throw new \UnexpectedValueException('No command entered');
        }

        return $commandName;
    }

    private function sortArguments(array $arguments): Arguments
    {
        $sortedArguments = [];

        foreach ($arguments as $argument) {
            if (\strpos($argument, '--') !== false) {
                $splitArguments = \explode('=', $argument);
                $this->validateArguments($splitArguments[1]);

                $splitArguments[0] = \str_replace('--', '', $splitArguments[0]);

                $sortedArguments[$splitArguments[0]] = $splitArguments[1];
            }
        }

        return Arguments::create($sortedArguments);
    }

    private function validateArguments(string $argument = null)
    {
        if ($argument === null || $argument === '') {
            throw new \InvalidArgumentException('No argument after --argumentName=');
        }
    }
}
