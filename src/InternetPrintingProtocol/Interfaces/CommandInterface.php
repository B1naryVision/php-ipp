<?php

namespace InternetPrintingProtocol\Interfaces;

interface CommandInterface
{
    /**
     * @return bool True = Successful, False = Failed
     */
    public function execute(): bool;

    /**
     * Example of wanted Details:.
     *
     * PHP_IPP = [
     *     'name' => 'Print',
     *     'description' => 'Prints a document',
     *     'arguments' => [
     *         'printer' => 'printer-name',
     *         'filePath' => 'path/to/file',
     *     ],
     * ]
     */
    public function getCommandDetails(): array;
}
