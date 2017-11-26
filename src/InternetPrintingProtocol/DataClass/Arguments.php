<?php

namespace InternetPrintingProtocol\DataClass;

class Arguments
{
    private $arguments;

    private function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public static function create(array $arguments): Arguments
    {
        return new self($arguments);
    }

    public function get(string $argumentName): string
    {
        if (isset($this->arguments[$argumentName])) {
            return $this->arguments[$argumentName];
        }

        throw new \InvalidArgumentException(
            \sprintf(
                'Argument %s does not exist',
                $argumentName
            )
        );
    }
}
