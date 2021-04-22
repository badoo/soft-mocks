<?php

$path = __DIR__;

$map = [
    'Badoo\SoftMock\Tests\Fixtures\InvalidRedefine' => $path . '/InvalidRedefine.phpi',
    'Badoo\SoftMock\Tests\Fixtures\ExceptionInBody' => $path . '/ExceptionInBody.phpi',
];

spl_autoload_register(
    static function ($class) use ($map) {
        if (isset($map[$class])) {
            require_once $map[$class];
        }
    }
);
