<?php

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony'                      => true,
        '@Symfony:risky'                => true,
        'array_syntax'                  => ['syntax' => 'short'],
        'class_definition'              => false,
        'concat_space'                  => ['spacing' => 'one'],
        'phpdoc_align'                  => false,
        'phpdoc_annotation_without_dot' => false,
        'yoda_style'                    => false,
        'no_break_comment'              => false,
        'self_accessor'                 => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('vendor')
            ->in(__DIR__)
    );
